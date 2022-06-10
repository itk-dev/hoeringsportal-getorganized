<?php

namespace App\GetOrganized;

use App\Entity\Archiver;
use App\Entity\ExceptionLogEntry;
use App\Exception\RuntimeException;
use App\Repository\GetOrganized\DocumentRepository;
use App\ShareFile\Item;
use App\ShareFile\ShareFileService;
use App\Traits\ArchiverAwareTrait;
use App\Util\TemplateHelper;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ArchiveHelper implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    use LoggerTrait;
    use ArchiverAwareTrait;

    protected static string $archiverType = Archiver::TYPE_SHAREFILE2GETORGANIZED;

    private ShareFileService $shareFile;

    private GetOrganizedService $getOrganized;

    private DocumentRepository $documentRepository;

    private EntityManagerInterface $entityManager;

    private TemplateHelper $templateHelper;

    private MailerInterface $mailer;

    private array $options;

    private const GET_ORGANIZED_CASE_ID_TICKET_KEY = 'go_case_id';

    public function __construct(ShareFileService $shareFile, GetOrganizedService $getOrganized, DocumentRepository $documentRepository, EntityManagerInterface $entityManager, TemplateHelper $templateHelper, MailerInterface $mailer)
    {
        $this->shareFile = $shareFile;
        $this->getOrganized = $getOrganized;
        $this->documentRepository = $documentRepository;
        $this->entityManager = $entityManager;
        $this->templateHelper = $templateHelper;
        $this->mailer = $mailer;
        $this->setLogger(new NullLogger());
    }

    public function archive(Archiver $archiver, $hearingItemId = null, array $options = [])
    {
        $this->setArchiver($archiver);
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $this->options = $resolver->resolve($options);

        try {
            $this->shareFile
                ->setArchiver($archiver)
                ->setLogger($this->logger);
            $this->getOrganized
                ->setArchiver($archiver)
                ->setLogger($this->logger);

            $startTime = new \DateTimeImmutable();

            $this->archiveResponses($hearingItemId);
            $this->archiveOverviews($hearingItemId);

            if (null === $hearingItemId) {
                $archiver->setLastRunAt($startTime);
                $this->entityManager->persist($archiver);
                $this->entityManager->flush();
            }
        } catch (\Throwable $t) {
            $this->logException($t);
        }
    }

    public function log($level, $message, array $context = [])
    {
        $this->logger->log($level, $message, $context);
    }

    private function archiveResponses(string $hearingItemId = null)
    {
        if (null !== $hearingItemId) {
            $this->info(sprintf('Getting hearing %s', $hearingItemId));
            $hearing = $this->shareFile->getHearing($hearingItemId);
            $shareFileData = [$hearing];
        } else {
            $date = $this->archiver->getLastRunAt() ?? new \DateTime('-1 month ago');
            $this->info(sprintf('Getting files updated since %s from ShareFile', $date->format(\DateTimeInterface::ATOM)));
            $shareFileData = $this->shareFile->getUpdatedFiles($date);
        }

        foreach ($shareFileData as $shareFileHearing) {
            $getOrganizedHearing = null;

            foreach ($shareFileHearing->getChildren() as $shareFileResponse) {
                try {
                    $sourceFile = null;

                    $caseWorker = null;
                    $departmentId = $shareFileResponse->metadata['ticket_data']['department_id'] ?? null;
                    $organisationReference = $this->archiver->getGetOrganizedOrganizationReference($departmentId);
                    if (null === $organisationReference) {
                        throw new RuntimeException(sprintf('Unknown department %s on item %s', $departmentId, $shareFileResponse->id));
                    }

                    if (null === $getOrganizedHearing) {
                        if ($this->archiver->getCreateHearing()) {
                            $this->info(sprintf('Getting hearing: %s', $shareFileHearing->name));
                            $shareFileHearing->metadata = $shareFileResponse->metadata;

                            $metadata = [];
                            // @todo
                            // if (null !== $caseWorker) {
                            //     $metadata['CaseFileManagerReference'] = $caseWorker['CaseWorkerId'];
                            // }
                            if (null !== $organisationReference) {
                                $metadata['OrganisationReference'] = $organisationReference;
                            }

                            // @todo
                            // $getOrganizedHearing = $this->getOrganized->getHearing($shareFileHearing, true, $metadata);
                            if (null === $getOrganizedHearing) {
                                throw new RuntimeException(sprintf('Error creating hearing: %s', $shareFileHearing->name));
                            }
                        } else {
                            $this->info(sprintf('Getting hearing for response %s', $shareFileResponse->name));
                            $getOrganizedCaseId = $shareFileResponse->metadata['ticket_data'][self::GET_ORGANIZED_CASE_ID_TICKET_KEY] ?? null;

                            if (null === $getOrganizedCaseId) {
                                throw new RuntimeException(sprintf('Cannot get GetOrganized case id from item %s (%s)', $shareFileResponse->name, $shareFileResponse->id));
                            }
                            $getOrganizedHearing = $this->getOrganized->getCaseById($getOrganizedCaseId);
                            if (null === $getOrganizedHearing) {
                                throw new RuntimeException(sprintf('Cannot get GetOrganized case %s', $getOrganizedCaseId));
                            }
                        }
                    }

                    $this->info($shareFileResponse->name);

                    $files = $this->shareFile->getFiles($shareFileResponse);
                    $pattern = $this->archiver->getConfigurationValue('[getorganized][sharefile_file_name_pattern]');
                    $sourceFiles = array_filter(
                            $files,
                            static function (Item $file) use ($pattern) {
                                return null === $pattern || fnmatch($pattern, $file->name);
                            }
                        );
                    if (null !== $pattern && empty($sourceFiles)) {
                        throw new RuntimeException(sprintf('Cannot find file matching pattern %s for item %s', $pattern, $shareFileResponse->id));
                    }

                    foreach ($sourceFiles as $sourceFile) {
                        $this->archiveDocument($sourceFile, $getOrganizedHearing, null, ['item_metadata' => ['name' => $shareFileResponse->name] + $shareFileResponse->metadata]);
                    }
                } catch (\Throwable $t) {
                    $this->logException($t, [
                            'shareFileHearing' => $shareFileHearing,
                            'getOrganizedHearing' => $getOrganizedHearing,
                            'sourceFile' => $sourceFile,
                        ]);
                }
            }
        }
    }

    private function archiveOverviews(string $hearingItemId = null)
    {
        $overviews = $this->archiver->getConfigurationValue('[getorganized][overview][items]');
        if (empty($overviews)) {
            $this->warning('No overviews defined');

            return;
        }

        // Overview files
        if (null !== $hearingItemId) {
            $this->info(sprintf('Getting overview files from hearing %s', $hearingItemId));
            $hearing = $this->shareFile->getHearingOverviewFiles($hearingItemId);
            $shareFileData = [$hearing];
        } else {
            $date = $this->archiver->getLastRunAt() ?? new \DateTime('-1 month ago');
            $this->info(sprintf('Getting overview files updated since %s from ShareFile', $date->format(\DateTimeInterface::ATOM)));
            $shareFileData = $this->shareFile->getUpdatedOverviewFiles($date);
        }

        foreach ($shareFileData as $shareFileHearing) {
            try {
                // Get GetOrganized hearing under which to archive.
                //
                // This hearing must be created previously by archiving a
                // response.

                $getOrganizedHearing = null;
                $shareFileResponses = $this->shareFile->getResponses($shareFileHearing);

                $caseWorker = null;
                $departmentId = null;
                foreach ($shareFileResponses as $shareFileResponse) {
                    if (!empty($shareFileResponse->metadata['ticket_data']['department_id'])) {
                        $departmentId = $shareFileResponse->metadata['ticket_data']['department_id'];

                        break;
                    }
                }

                $organisationReference = $this->archiver->getGetOrganizedOrganizationReference($departmentId);
                if (null === $organisationReference) {
                    throw new RuntimeException(sprintf('Unknown department %s on item %s', $departmentId, $shareFileHearing->id));
                }

                if ($this->archiver->getCreateHearing()) {
                    // @todo
                    // $getOrganizedHearing = $this->getOrganized->getHearing($shareFileHearing);
                    if (null === $getOrganizedHearing) {
                        throw new RuntimeException(sprintf('Cannot get GetOrganized case %s', $shareFileHearing->id));
                    }
                } else {
                    $getOrganizedCaseId = null;
                    foreach ($shareFileResponses as $shareFileResponse) {
                        if (!empty($shareFileResponse->metadata['ticket_data'][self::GET_ORGANIZED_CASE_ID_TICKET_KEY])) {
                            $getOrganizedCaseId = $shareFileResponse->metadata['ticket_data'][self::GET_ORGANIZED_CASE_ID_TICKET_KEY];

                            break;
                        }
                    }

                    if (null === $getOrganizedCaseId) {
                        throw new RuntimeException(sprintf('Cannot get GetOrganized case id from item %s (%s)', $shareFileHearing->name, $shareFileHearing->id));
                    }

                    $getOrganizedHearing = $this->getOrganized->getCaseById($getOrganizedCaseId);
                    if (null === $getOrganizedHearing) {
                        throw new RuntimeException(sprintf('Cannot get GetOrganized case %s', $getOrganizedCaseId));
                    }
                }

                foreach ($overviews as $overview) {
                    $pattern = $overview['pattern'] ?? null;
                    $titleTemplate = $overview['title'] ?? '{{ item.name }}';

                    try {
                        $sourceFile = null;

                        if (null !== $pattern) {
                            $files = $shareFileHearing->getChildren();
                            foreach ($files as $file) {
                                if (fnmatch($pattern, $file['Name'])) {
                                    $sourceFile = $file;
                                    break;
                                }
                            }
                        }

                        if (null === $sourceFile) {
                            $this->warning(sprintf('Overview file not found: %s (%s)', $pattern, $shareFileHearing->id));
                            continue;
                        }

                        $title = $this->templateHelper->render($titleTemplate, ['item' => $shareFileHearing]);
                        $this->archiveDocument($sourceFile, $getOrganizedHearing, $title);
                    } catch (\Throwable $t) {
                        $this->logException($t, [
                            'shareFileHearing' => $shareFileHearing,
                            'getOrganizedHearing' => $getOrganizedHearing,
                            'sourceFile' => $sourceFile,
                            'overview' => $overview,
                        ]);
                    }
                }
            } catch (\Throwable $t) {
                $this->logException($t, [
                    'shareFileHearing' => $shareFileHearing,
                ]);
            }
        }
    }

    private function archiveDocument(Item $sourceFile, CaseEntity $getOrganizedHearing, string $title = null, array $options = [])
    {
        $metadata = [];

        if (null === $title) {
            $title = $sourceFile->name;
        } else {
            $metadata['ows_Title'] = $title;
        }

        $this->info(sprintf('Archiving document %s (%s)', $title, $sourceFile->id));

        $document = $this->documentRepository->findOneByItemAndArchiver($sourceFile, $this->archiver);
        if (null === $document) {
            // Try to find document by filename.
            $this->info(sprintf('Looking for document by filename (%s on case %s)', $sourceFile->fileName, $getOrganizedHearing->id));
            $getOrganizedDocument = $this->getOrganized->getDocumentByFilename($getOrganizedHearing->id, $sourceFile->fileName);
            if (null !== $getOrganizedDocument) {
                $this->info(sprintf('Found document by filename (%s on case %s): %s', $sourceFile->fileName, $getOrganizedHearing->id, $getOrganizedDocument->docId));

                $document = $this->getOrganized->linkDocument(
                    $getOrganizedHearing,
                    $getOrganizedDocument,
                    $sourceFile,
                    $metadata,
                    $this->archiver
                );
            }
        }

        if (null === $document) {
            $this->info(sprintf('Creating new document in GetOrganized (%s)', $title));

            // @todo
            // if (null !== $caseWorker) {
            //     $metadata['CaseManagerReference'] = $caseWorker['CaseWorkerId'];
            // }
            // if (null !== $organisationReference) {
            //     $metadata['OrganisationReference'] = $organisationReference;
            // }

            $fileContents = $this->getFileContents($sourceFile);
            $document = $this->getOrganized->createDocument(
                $fileContents,
                $getOrganizedHearing,
                $sourceFile,
                $metadata,
                [
                    'item_metadata' => $options['item_metadata'] ?? [],
                ]
            );
        } else {
            $sourceFileCreatedAt = new \DateTimeImmutable($sourceFile->creationDate);
            if ($this->force() || $document->getUpdatedAt() < $sourceFileCreatedAt) {
                $this->info(sprintf('Updating document in GetOrganized (%s)', $title));

                $fileContents = $this->getFileContents($sourceFile);
                $document = $this->getOrganized->updateDocument(
                    $document,
                    $fileContents,
                    $getOrganizedHearing,
                    $sourceFile,
                    $metadata,
                    [
                        'item_metadata' => $options['item_metadata'] ?? [],
                    ]
                );
            } else {
                $this->info(sprintf('Document in GetOrganized is already up to date (%s)', $title));
            }
        }

        unset($fileContents);

        if (null === $document) {
            throw new RuntimeException(sprintf('Error creating document in GetOrganized (%s; %s)', $title, $sourceFile->id));
        }
    }

    private function getFileContents(Item $sourceFile)
    {
        $this->info(sprintf('Getting file contents from ShareFile (%s; %s)', $sourceFile->id, $sourceFile->fileName));

        $fileContents = $this->shareFile->downloadFile($sourceFile);

        if (null === $fileContents) {
            throw new RuntimeException(sprintf('Cannot get file contents for item %s', $sourceFile->id));
        }

        $this->debug(sprintf('File size: %d', strlen($fileContents)));

        return $fileContents;
    }

    private function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'force' => false,
        ]);
    }

    private function force(): bool
    {
        return true === $this->options['force'];
    }

    private function logException(\Throwable $t, array $context = [])
    {
        $this->emergency($t->getMessage(), $context);
        $logEntry = new ExceptionLogEntry($t, $context);
        $this->entityManager->persist($logEntry);
        $this->entityManager->flush();

        if (null !== $this->archiver) {
            $config = $this->archiver->getConfigurationValue('[notifications][email]');

            if (null !== $config) {
                $email = (new Email())
                       ->from($config['from'])
                       ->to(...$config['to'])
                       ->subject($t->getMessage())
                       ->text(
                           implode(PHP_EOL, [
                               json_encode($context, JSON_PRETTY_PRINT),
                               $t->getTraceAsString(),
                           ])
                       );

                $this->mailer->send($email);
            }
        }
    }
}

<?php

namespace App\GetOrganized;

use App\Entity\Archiver;
use App\Entity\ExceptionLogEntry;
use App\Exception\RuntimeException;
use App\Repository\GetOrganized\DocumentRepository;
use App\ShareFile\ShareFileService;
use App\Traits\ArchiverAwareTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerTrait;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ArchiveHelper
{
    use LoggerAwareTrait;
    use LoggerTrait;
    use ArchiverAwareTrait;

    protected string $archiverType = Archiver::TYPE_SHAREFILE2GETORGANIZED;

    private ShareFileService $shareFile;

    private GetOrganizedService $getOrganized;

    private DocumentRepository $documentRepository;

    private EntityManagerInterface $entityManager;

    private MailerInterface $mailer;

    public function __construct(ShareFileService $shareFile, GetOrganizedService $getOrganized, DocumentRepository $documentRepository, EntityManagerInterface $entityManager, MailerInterface $mailer)
    {
        $this->shareFile = $shareFile;
        $this->getOrganized = $getOrganized;
        $this->documentRepository = $documentRepository;
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
    }

    public function archive(Archiver $archiver, $hearingItemId = null)
    {
        $this->setArchiver($archiver);

        try {
            $this->shareFile->setArchiver($archiver);
            $this->getOrganized->setArchiver($archiver);

            $startTime = new \DateTime();
            $date = null;

            // @FIXME: Getting data from ShareFile should be moved into a service/helper.
            if (null !== $hearingItemId) {
                $this->info(sprintf('Getting hearing %s', $hearingItemId));
                $hearing = $this->shareFile->getHearing($hearingItemId);
                $shareFileData = [$hearing];
            } else {
                $date = $archiver->getLastRunAt() ?? new \DateTime('-1 month ago');
                $this->info(sprintf('Getting files updated since %s from ShareFile', $date->format(\DateTimeInterface::ATOM)));
                $shareFileData = $this->shareFile->getUpdatedFiles($date);
            }

            foreach ($shareFileData as $shareFileHearing) {
                $getOrganizedHearing = null;

                foreach ($shareFileHearing->getChildren() as $shareFileResponse) {
                    try {
                        $caseWorker = null;
                        $departmentId = $shareFileResponse->metadata['ticket_data']['department_id'] ?? null;
                        $organisationReference = $archiver->getGetOrganizedOrganizationReference($departmentId);
                        if (null === $organisationReference) {
                            throw new RuntimeException(sprintf('Unknown department %s on item %s', $departmentId, $shareFileResponse->id));
                        }

                        if (null === $getOrganizedHearing) {
                            if ($archiver->getCreateHearing()) {
                                $this->info('Getting hearing: '.$shareFileHearing->name);
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
                                    throw new RuntimeException('Error creating hearing: '.$shareFileHearing['Name']);
                                }
                            } else {
                                $this->info(sprintf('Getting hearing for response %s', $shareFileResponse->name));
                                $getOrganizedCaseId = $shareFileResponse->metadata['ticket_data']['get_organized_case_id'] ?? null;

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
                        $document = $this->documentRepository->findOneByItemAndArchiver($shareFileResponse, $this->archiver);
                        $this->info('Getting file contents from ShareFile');

                        $sourceFile = null;
                        $sourceFileCreatedAt = null;
                        $pattern = $this->archiver->getConfigurationValue('[getorganized][sharefile_file_name_pattern]');
                        if (null !== $pattern) {
                            $files = $this->shareFile->getFiles($shareFileResponse);
                            foreach ($files as $file) {
                                if (fnmatch($pattern, $file['Name'])) {
                                    $sourceFile = $file;
                                    $sourceFileCreatedAt = new \DateTime($file->creationDate);
                                }
                            }
                            if (null === $sourceFile) {
                                throw new RuntimeException(sprintf('Cannot find file matching pattern %s for item %s', $pattern, $shareFileResponse->id));
                            }
                        } else {
                            $sourceFile = $shareFileResponse;
                            $sourceFileCreatedAt = $shareFileResponse->creationDate;
                        }
                        $fileContents = $this->shareFile->downloadFile($sourceFile);
                        if (null === $fileContents) {
                            throw new RuntimeException(sprintf('Cannot get file contents for item %s', $shareFileResponse->id));
                        }
                        $metadata = [];
                        if (null === $document) {
                            $this->info('Creating new document in GetOrganized');

                            // @todo
                            // if (null !== $caseWorker) {
                            //     $metadata['CaseManagerReference'] = $caseWorker['CaseWorkerId'];
                            // }
                            if (null !== $organisationReference) {
                                $metadata['OrganisationReference'] = $organisationReference;
                            }

                            $document = $this->getOrganized->createDocument($fileContents, $getOrganizedHearing, $shareFileResponse, $metadata);
                        } else {
                            if ($document->getUpdatedAt() < $sourceFileCreatedAt) {
                                $this->info('Updating document in GetOrganized');
                                $this->getOrganized->updateDocument(
                                    $fileContents,
                                    $getOrganizedHearing,
                                    $shareFileResponse,
                                    $metadata
                                );
                            } else {
                                $this->info('Document in GetOrganized is already up to date');
                            }
                        }
                        if (null === $document) {
                            throw new RuntimeException(sprintf('Error creating response %s', $shareFileResponse['Name']));
                        }
                    } catch (\Throwable $t) {
                        $this->logException($t, [
                            'shareFileHearing' => $shareFileHearing,
                            'shareFileResponse' => $shareFileResponse,
                        ]);
                    }
                }
            }

            // Overview files
            if (null !== $hearingItemId) {
                $this->info(sprintf('Getting overview files from hearing %s', $hearingItemId));
                $hearing = $this->shareFile->getHearingOverviewFiles($hearingItemId);
                $shareFileData = [$hearing];
            } else {
                $date = $archiver->getLastRunAt() ?? new \DateTime('-1 month ago');
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

                    $organisationReference = $archiver->getGetOrganizedOrganizationReference($departmentId);
                    if (null === $organisationReference) {
                        throw new RuntimeException(sprintf('Unknown department %s on item %s', $departmentId, $shareFileHearing->id));
                    }

                    if ($archiver->getCreateHearing()) {
                        // @todo
                        // $getOrganizedHearing = $this->getOrganized->getHearing($shareFileHearing);
                        if (null === $getOrganizedHearing) {
                            throw new RuntimeException(sprintf('Cannot get GetOrganized case %s', $shareFileHearing->id));
                        }
                    } else {
                        $getOrganizedCaseId = null;
                        foreach ($shareFileResponses as $shareFileResponse) {
                            if (!empty($shareFileResponse->metadata['ticket_data']['get_organized_case_id'])) {
                                $getOrganizedCaseId = $shareFileResponse->metadata['ticket_data']['get_organized_case_id'];

                                break;
                            }
                        }

                        if (null === $getOrganizedCaseId) {
                            throw new RuntimeException(sprintf('Cannot get GetOrganized case id from item %s (%s)', $shareFileHearing->name, $shareFileHearing->id));
                        }

                        $getOrganizedHearing = $this->getOrganized->getCaseById($getOrganizedCaseId);
                        if (null === $getOrganizedHearing) {
                            throw new RuntimeException(sprintf('Cannot get GetOrganized case %s', $shareFileHearing->id));
                        }
                    }

                    $overviews = [
                        [
                            'pattern' => $this->archiver->getConfigurationValue('[getorganized][sharefile_file_combined_name_pattern]', '*-combined.pdf'),
                            'title' => sprintf('%s - samlede høringssvar', $shareFileHearing->getName()),
                        ],
                        [
                            'pattern' => $this->archiver->getConfigurationValue('[getorganized][sharefile_file_overview_name_pattern]', 'overblik.xlsx'),
                            'title' => sprintf('%s - overblik over høringssvar', $shareFileHearing->getName()),
                        ],
                    ];
                    foreach ($overviews as $overview) {
                        $pattern = $overview['pattern'] ?? null;
                        $title = $overview['title'];

                        try {
                            $this->info(sprintf('Getting overview file "%s" (%s) from ShareFile', $title, $pattern));

                            $sourceFile = null;
                            $sourceFileCreatedAt = null;
                            if (null !== $pattern) {
                                $files = $shareFileHearing->getChildren();
                                foreach ($files as $file) {
                                    if (fnmatch($pattern, $file['Name'])) {
                                        $sourceFile = $file;
                                        $sourceFileCreatedAt = new \DateTime($file->creationDate);
                                        break;
                                    }
                                }
                            }

                            if (null !== $sourceFile) {
                                $fileContents = $this->shareFile->downloadFile($sourceFile);
                                if (null === $fileContents) {
                                    throw new RuntimeException(sprintf('Cannot get file contents for item %s', $sourceFile->id));
                                }

                                $document = $this->documentRepository->findOneByItemAndArchiver($sourceFile, $this->archiver);

                                $metadata = [];
                                if (null === $document) {
                                    $this->info(sprintf('Creating new document in GetOrganized (%s)', $title));
                                    // @todo
                                    // if (null !== $caseWorker) {
                                    //     $metadata['CaseManagerReference'] = $caseWorker['CaseWorkerId'];
                                    // }
                                    if (null !== $organisationReference) {
                                        $metadata['OrganisationReference'] = $organisationReference;
                                    }

                                    $document = $this->getOrganized->createDocument($fileContents, $getOrganizedHearing, $sourceFile, $metadata);
                                } else {
                                    if ($document->getUpdatedAt() < $sourceFileCreatedAt) {
                                        $this->info(sprintf('Updating document in GetOrganized (%s)', $title));
                                        $document = $this->getOrganized->updateDocument(
                                            $fileContents,
                                            $getOrganizedHearing,
                                            $sourceFile,
                                            $metadata
                                        );
                                    } else {
                                        $this->info('Document in GetOrganized is already up to date');
                                    }
                                }
                                if (null === $document) {
                                    throw new RuntimeException(sprintf('Error creating overview "%s" (%s)', $title, $shareFileHearing['Name']));
                                }
                            } else {
                                $this->warning(sprintf('Overview file not found: %s (%s)', $title, $pattern));
                            }
                        } catch (\Throwable $t) {
                            $this->logException($t, [
                                'shareFileHearing' => $shareFileHearing,
                                'getOrganizedHearing' => $getOrganizedHearing,
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
        if (null !== $this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }

    private function logException(\Throwable $t, array $context = [])
    {
        $this->emergency($t->getMessage(), $context);
        $logEntry = new ExceptionLogEntry($t, $context);
        $this->entityManager->persist($logEntry);
        $this->entityManager->flush();

        if (null !== $this->archiver) {
            $config = $this->archiver->getConfigurationValue('[notifications][email]');

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

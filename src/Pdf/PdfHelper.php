<?php

namespace App\Pdf;

use App\Entity\Archiver;
use App\Entity\ExceptionLogEntry;
use App\Repository\ArchiverRepository;
use App\ShareFile\Item;
use App\ShareFile\ShareFileService;
use App\Traits\ArchiverAwareTrait;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Mpdf\Mpdf;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use setasign\Fpdi\PdfParser\StreamReader;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

class PdfHelper
{
    use LoggerAwareTrait;
    use LoggerTrait;
    use ArchiverAwareTrait;

    protected static string $archiverType = Archiver::TYPE_SHAREFILE2GETORGANIZED;

    private const GROUP_DEFAULT = 'Privatperson';

    private ArchiverRepository $archiverRepository;

    private ShareFileService $shareFileService;

    private Filesystem $filesystem;

    private Environment $twig;

    private EntityManagerInterface $entityManager;

    private MailerInterface $mailer;

    private array $options;

    public function __construct(
        ArchiverRepository $archiverRepository,
        ShareFileService $shareFileService,
        Filesystem $filesystem,
        Environment $twig,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        array $options
    ) {
        $this->archiverRepository = $archiverRepository;
        $this->shareFileService = $shareFileService;
        $this->filesystem = $filesystem;
        $this->twig = $twig;
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->setLogger(new NullLogger());

        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $this->options = $resolver->resolve($options);
    }

    public function process()
    {
        if (null === $this->getArchiver()) {
            throw new \RuntimeException('No archiver');
        }

        try {
            $startTime = new \DateTime();
            $hearings = $this->getFinishedHearings();
            foreach ($hearings as $hearing) {
                try {
                    $hearingId = 'H'.$hearing['hearing_id'];
                    $this->run($hearingId, $hearing);
                } catch (\Throwable $t) {
                    $this->logException($t, ['hearing' => $hearing]);
                }
            }
            $this->archiver->setLastRunAt($startTime);
            $this->entityManager->persist($this->archiver);
            $this->entityManager->flush();
        } catch (\Throwable $t) {
            $this->logException($t);
        }
    }

    public function run($hearingId, array $metadata = null)
    {
        if (null === $this->getArchiver()) {
            throw new \RuntimeException('No archiver');
        }

        $result = $this->getData($hearingId, $metadata);
        $this->info('get-data: '.(is_scalar($result) ? $result : json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));

        $result = $this->combine($hearingId);
        $this->info('combine: '.(is_scalar($result) ? $result : json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));

        $result = $this->share($hearingId);
        $this->info('share: '.(is_scalar($result) ? $result : json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));

        return $result;
    }

    public function getData($hearingId, array $metadata = null)
    {
        if (null === $this->getArchiver()) {
            throw new \RuntimeException('No archiver');
        }
        $this->debug('Getting hearing '.$hearingId);
        $hearing = $this->shareFileService->findHearing($hearingId);
        $hearing->metadata['api_data'] = $metadata;
        $this->debug('Getting responses');
        $responses = $this->getResponses($hearing);

        $this->debug('Getting file data');
        // Pdf files indexed by response id.
        $files = [];
        $fileNamePattern = $this->archiver->getConfigurationValue('[file_name_pattern]', '*-offentlig*.pdf');

        foreach ($responses as $response) {
            $responseFiles = $this->shareFileService->getFiles($response);
            $responseFiles = array_filter($responseFiles, function (Item $file) use ($fileNamePattern) {
                return fnmatch($fileNamePattern, $file->name);
            });
            if (0 < \count($responseFiles)) {
                $file = reset($responseFiles);
                $this->debug($file->getId());
                $files[$response->getId()] = $file;
            }
        }

        // Remove responses with no pdf file.
        $responses = array_filter($responses, function (Item $response) use ($files) {
            return isset($files[$response->getId()]);
        });

        $filename = $this->getDataFilename($hearingId);

        // Build hearing metadata.
        $hearing = json_decode(json_encode($hearing), true);
        if (null === $metadata) {
            $metadata = $this->getHearing($hearingId);
        }
        $hearing['_metadata'] = $metadata;

        $this->debug('Writing datafile '.$filename);
        $this->filesystem->dumpFile($filename, json_encode([
            'archiver' => $this->getArchiver(),
            'hearing' => $hearing,
            'responses' => $responses,
            'files' => $files,
        ]));

        return $filename;
    }

    public function combine($hearingId)
    {
        $data = $this->getHearingData($hearingId);
        $archiver = $this->loadArchiver($data);
        $this->shareFileService->setArchiver($archiver);

        return $this->buildCombinedPdf($data);
    }

    public function share($hearingId)
    {
        $data = $this->getHearingData($hearingId);
        $archiver = $this->loadArchiver($data);
        $this->shareFileService->setArchiver($archiver);
        $filename = $this->getDataFilename($hearingId, '-combined.pdf');
        if (!$this->filesystem->exists($filename)) {
            throw new \RuntimeException('Cannot find file to share for hearing '.$hearingId);
        }

        $parentId = $this->getHearingValue($data, 'Id');

        $this->debug(sprintf('Sharing file %s to %s', $filename, $parentId));

        $result = $this->shareFileService->uploadFile($filename, $parentId);

        // @see https://api.sharefile.com/docs/resource?name=Items#Upload_File
        if (0 !== strpos($result, 'OK')) {
            throw new \RuntimeException('Error uploading file: '.$filename);
        }

        try {
            $result = $this->shareFileService->findFile(basename($filename), $parentId);
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Cannot get shared file %s in %s', $filename, $parentId));
        }

        return $result;
    }

    public function buildCombinedPdf(array $data)
    {
        $this->debug('Downloading pdf files');
        $dirname = $this->downloadFiles($data);
        $this->debug('Combining pdf files');

        // Disable the garbage collector to prevent "feof(): supplied resource
        // is not a valid stream resource" errors.
        gc_disable();
        $filename = $this->combineFiles($data, $dirname);
        gc_enable();
        gc_collect_cycles();

        return $filename;
    }

    public function setArchiver($archiver)
    {
        if (\is_string($archiver)) {
            $archiver = $this->archiverRepository->find($archiver);
            if (null === $archiver) {
                throw new \RuntimeException('Invalid archiver: '.$archiver);
            }
        }
        $this->archiver = $archiver;
        $this->shareFileService->setArchiver($archiver);
    }

    public function log($level, $message, array $context = [])
    {
        if (null !== $this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }

    public function getLogFilename($hearingId, $id)
    {
        return $this->getDataFilename($hearingId, '-'.$id.'.log');
    }

    /**
     * Get data filename.
     */
    private function getDataFilename(string $hearingId, string $suffix = '.json'): string
    {
        $directory = $this->getDataDirectory($hearingId);
        $filename = $directory.'/'.$hearingId.$suffix;

        $this->filesystem->mkdir(\dirname($filename));

        return $filename;
    }

    private function getHearingData($hearingId)
    {
        $filename = $this->getDataFilename($hearingId);
        if (!$this->filesystem->exists($filename)) {
            throw new \RuntimeException('Cannot find datafile for hearing '.$hearingId);
        }
        $data = file_get_contents($filename);

        return json_decode($data, true);
    }

    private function loadArchiver($id)
    {
        if (isset($id['archiver']['id'])) {
            $id = $id['archiver']['id'];
        }

        $archiver = $this->archiverRepository->findOneByNameOrId($id);

        if (null === $archiver) {
            throw new \RuntimeException('Invalid archiver: '.$id);
        }

        return $archiver;
    }

    /**
     * Download files from ShareFile.
     *
     * @throws \Exception
     *
     * @return string|null
     */
    private function downloadFiles(array $data)
    {
        try {
            $hearingId = $this->getHearingValue($data, 'Name');

            $dirname = $this->getDataDirectory($hearingId);
            $this->filesystem->mkdir($dirname);

            $index = 0;
            foreach ($data['files'] as $responseId => $item) {
                ++$index;
                if (empty($item)) {
                    continue;
                }
                $item = new Item($item);
                $filename = $this->getPdfFilename($dirname, $responseId);

                if ($this->filesystem->exists($filename)) {
                    $itemCreationTime = new \DateTime($item->creationDate);
                    $fileMtime = new \DateTime();
                    $fileMtime->setTimestamp(filemtime($filename));
                    if ($fileMtime > $itemCreationTime) {
                        $this->debug(sprintf(
                            '% 4d/%d File %s already downloaded (%s)',
                            $index,
                            \count($data['files']),
                            $item->getId(),
                            $filename
                        ));

                        continue;
                    }
                }
                $this->debug(sprintf(
                    '% 4d/%d Downloading file %s (%s)',
                    $index,
                    \count($data['files']),
                    $item->getId(),
                    $filename
                ));
                $contents = $this->shareFileService->downloadFile($item);
                $this->filesystem->dumpFile($filename, $contents);
            }

            return rtrim($dirname, '/');
        } catch (IOExceptionInterface $exception) {
            $this->log(
                LogLevel::EMERGENCY,
                'An error occurred while creating your directory at '.$exception->getPath()
            );
        }

        return null;
    }

    private function getDataDirectory($path = null)
    {
        $directory = $this->options['project_dir'].'/var/pdf';

        if (null !== $path) {
            $directory .= '/'.$path;
        }

        return $directory;
    }

    private function getPdfFilename(string $directory, $item)
    {
        $id = $item instanceof Item ? $item->getId() : $item;

        return $directory.'/'.$id.'.pdf';
    }

    private function getHearingValue(array $data, $key = null)
    {
        if (null !== $key) {
            if (!isset($data['hearing'][$key])) {
                throw new \OutOfBoundsException('No such key: '.$key);
            }

            return $data['hearing'][$key];
        }

        return $data['hearing'];
    }

    private function combineFiles(array $data, string $directory)
    {
        $hearingId = $this->getHearingValue($data, 'Name');

        $mpdf = new Mpdf();

        $this->debug('Generating front page');
        $frontPage = $this->generateFrontpage($data);
        $mpdf->WriteHTML($frontPage);

        $this->debug('Adding table of contents');

        $groups = $this->getResponseGroups($data['responses']);

        $total = 0;
        foreach ($groups as $group => $responses) {
            $total += \count($responses);
        }

        $mpdf->TOCpagebreakByArray([
            'links' => true,
            'toc-orientation' => 'portrait',
            'toc-sheet-size' => 'A4',
        ]);

        $index = 0;
        $tocGroup = null;
        foreach ($groups as $group => $responses) {
            $this->debug(sprintf('Group: %s', $group));
            $tocName = $this->getTOCName($group);
            if ($tocGroup !== $group) {
                $mpdf->TOC_Entry($group, 0);
            }

            foreach ($responses as $response) {
                ++$index;
                $response = new Item($response);
                $filename = $this->getPdfFilename($directory, $response);
                if (!$this->filesystem->exists($filename)) {
                    continue;
                }

                $reader = StreamReader::createByFile($filename);
                $pagecount = $mpdf->setSourceFile($reader);
                $this->debug(sprintf('% 4d/%d Adding file %s', $index, $total, $filename));

                for ($p = 1; $p <= $pagecount; ++$p) {
                    $tplId = $mpdf->ImportPage($p);
                    $size = $mpdf->GetTemplateSize($tplId);

                    if ($index > 1 || $p > 1) {
                        $mpdf->AddPageByArray([
                            'orientation' => $size['width'] > $size['height'] ? 'L' : 'P',
                            // Make room for page footer.
                            'sheet-size' => [$size['width'], $size['height'] + 20],
                        ]);
                    }
                    if (1 === $p) {
                        $title = $this->getTitle($response) ?? $response->getName() ?? $response->getId();
                        $title .= ' – '.$this->getHearingReplyId($response);
                        $mpdf->TOC_Entry($title, 1);
                    }

                    $mpdf->useTemplate($tplId);
                }
            }
        }

        $filename = $this->getDataFilename($hearingId, '-combined.pdf');
        $this->debug(sprintf('Writing combined pdf to %s', $filename));
        $mpdf->Output($filename);

        return $filename;
    }

    private function getTOCName($name)
    {
        return base64_encode($name);
    }

    private function getTitle($response)
    {
        if (!isset($response['_metadata']['user_data']['name'])) {
            return null;
        }

        return $this->isOrganizationResponse($response)
            ? $response['_metadata']['ticket_data']['on_behalf_organization'].' (v. '.$response['_metadata']['user_data']['name'].')'
            : $response['_metadata']['user_data']['name'];
    }

    private function getHearingReplyId($response)
    {
        return $response['_metadata']['ticket_data']['ref'] ?? null;
    }

    /**
     * Get responses indexed by item id.
     */
    private function getResponses(Item $hearing): array
    {
        $responses = $this->shareFileService->getResponses($hearing);

        // Remove responses that are marked as unpublished.
        $responses = array_filter($responses, function (Item $response) {
            return !isset($response->metadata['ticket_data']['unpublish_reply'])
                || 'Checked' !== $response->metadata['ticket_data']['unpublish_reply'];
        });

        // Index by item id.
        return array_combine(
            array_column($responses, 'id'),
            $responses
        );
    }

    private function getResponseGroups(array $responses)
    {
        $groups = [];
        foreach ($responses as $response) {
            $group = $response['_metadata']['ticket_data']['on_behalf'] ?? self::GROUP_DEFAULT;
            $groups[$group][] = $response;
        }

        // Sort responses in groups.
        $compareItemsByPersonName = function (array $a, array $b) {
            return strcasecmp(
                $a['_metadata']['user_data']['name'] ?? '',
                $b['_metadata']['user_data']['name'] ?? ''
            );
        };

        foreach ($groups as $name => &$responses) {
            usort($responses, function (array $a, array $b) {
                return strcasecmp($this->getTitle($a), $this->getTitle($b));
            });
        }

        // Sort groups by name and make sure that "Privatperson" comes last.
        uksort($groups, function (string $a, string $b) {
            if (self::GROUP_DEFAULT === $a) {
                return 1;
            }
            if (self::GROUP_DEFAULT === $b) {
                return -1;
            }

            return strcasecmp($a, $b);
        });

        return $groups;
    }

    private function isOrganizationResponse($response)
    {
        if (\is_array($response)) {
            return isset($response['_metadata']['ticket_data']['on_behalf_organization']);
        } elseif ($response instanceof Item) {
            return isset($response->metadata['ticket_data']['on_behalf_organization']);
        }

        return false;
    }

    private function isPrivateResponse($response)
    {
        return !$this->isOrganizationResponse($response);
    }

    private function generateFrontPage(array $data)
    {
        $template = 'pdf/frontpage.html.twig';
        $data['context'] = [
            'template_dir' => $this->options['project_dir'].'/templates/pdf',
            'template_base_url' => 'file://'.$this->options['project_dir'].'/templates/pdf',
        ];

        return $this->twig->render($template, $data);
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

    private function getHearings(array $hearingIds = null)
    {
        $this->logger->info('Getting all hearings');
        $config = $this->archiver->getConfigurationValue('hearings');
        if (!isset($config['api_url'])) {
            throw new \RuntimeException('Missing hearings api url');
        }

        $hearings = [];

        $client = new Client();

        $url = $config['api_url'];
        while (null !== $url) {
            $this->logger->debug(sprintf('api url: %s', $url));
            $response = $client->get($url);
            $data = json_decode((string) $response->getBody(), true);

            $hearings[] = array_map(function ($feature) {
                return $feature['properties'];
            }, $data['features']);

            // Parse link header (cf. https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Link).
            $next = null;
            $link = $response->getHeader('link');
            $rels = reset($link);
            if ($rels && preg_match_all('/<(?P<url>[^>]+)>; rel="(?P<rel>[^"]+)"/', $rels, $matches, PREG_SET_ORDER)) {
                $next = array_values(array_filter($matches, static function ($match) {
                    return 'next' === $match['rel'];
                }))[0] ?? null;
            }

            $url = $next['url'] ?? null;
        }

        // Flatten.
        $hearings = array_merge(...$hearings);

        if (!empty($hearingIds)) {
            $hearings = array_filter($hearings, static function ($properties) use ($hearingIds) {
                return \in_array($properties['hearing_id'], $hearingIds, true);
            });
        }

        return $hearings;
    }

    private function getFinishedHearings()
    {
        $hearings = $this->getHearings();

        $to = new \DateTime();
        $lastRunAt = $this->archiver->getLastRunAt() ?? new \DateTime('2001-01-01');
        $from = new \DateTime($lastRunAt->format(\DateTimeInterface::ATOM));
        // Allow changes on hearings after reply deadline.
        try {
            $from->modify($this->options['hearing_reply_deadline_offset']);
        } catch (\Throwable $t) {
        }

        // Get hearings finished since last run.
        $hearings = array_filter(
            $hearings,
            function ($hearing) use ($from, $to) {
                $deadline = new \DateTime($hearing['hearing_reply_deadline']);

                return $from <= $deadline && $deadline < $to;
            }
        );

        // Keep only hearings with new content in ShareFile.
        $hearings = array_filter(
            $hearings,
            function ($hearing) use ($lastRunAt) {
                try {
                    $hearingId = 'H'.$hearing['hearing_id'];
                    $hearing = $this->shareFileService->findHearing($hearingId);
                    $lastChangeAt = new \DateTime($hearing['ProgenyEditDate']);

                    return $lastChangeAt >= $lastRunAt;
                } catch (\Throwable $t) {
                    return false;
                }
            }
        );

        return $hearings;
    }

    private function getHearing($hearingId)
    {
        $hearings = $this->getHearings();
        $id = (int) preg_replace('/^[^\d]+/', '', $hearingId);

        foreach ($hearings as $hearing) {
            if ($id === $hearing['hearing_id']) {
                return $hearing;
            }
        }

        return null;
    }

    private function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired([
            'project_dir',
            'hearing_reply_deadline_offset',
        ]);
    }
}

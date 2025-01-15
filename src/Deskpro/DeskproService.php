<?php

namespace App\Deskpro;

use Deskpro\API\DeskproClient;
use GuzzleHttp\Client;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DeskproService
{
    private ?DeskproClient $client = null;

    private array $options;

    private static array $customFields = [
        'hearing_id' => '28',
        'hearing_name' => '30',
        'edoc_id' => '15',
        'pdf_download_url' => '22',
        'representation' => '2',
        'organization' => '7',
        'address_secret' => '32',
        'address' => '1',
        'postal_code' => '37',
        'geolocation' => '31',
        'message' => '35',
        'files' => '36',
        'accept_terms' => '11',
        'unpublish_reply' => '18',
        'resume' => '33',
        'vurdering' => '34',
        'udtaler' => '2',
    ];

    public function __construct(array $options)
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $this->options = $resolver->resolve($options);
    }

    public function getReplyData(string $replyId): array
    {
        $response = $this->client()->get('/ticket_custom_fields/{id}', ['id' => $replyId]);

        return $response->getData();
    }

    public function getHearingTickets(int $hearingId): array
    {
        $tickets = [];

        $currentPage = 1;
        $totalPages = 1;

        $query = [
            'count' => 200,
            'ticket_field.'.self::$customFields['hearing_id'] => $hearingId,
            'include' => 'person',
        ];
        while ($currentPage <= $totalPages) {
            $query['page'] = $currentPage;
            $endpoint = '/tickets?'.http_build_query($query);
            $response = $this->client()->get($endpoint);
            $meta = $response->getMeta();
            if ($meta['pagination']['total'] > 999) {
                throw new \RuntimeException('Cannot reliably handle more than 999 tickets due to limitation in the Deskpro API');
            }

            $totalPages = $meta['pagination']['total_pages'];
            ++$currentPage;

            $linked = $response->getLinked();
            foreach ($response->getData() as $item) {
                // Expand person in ticket from linked data.
                if (isset($item['person'], $linked['person'], $linked['person'][$item['person']])) {
                    $item['person'] = $linked['person'][$item['person']];
                }

                // Add readable names to field values
                foreach (self::$customFields as $name => $id) {
                    $item['fields'][$name] = $item['fields'][$id]['detail']
                                             ?? $item['fields'][$name] = $item['fields'][$id]['detail']
                                             ?? $item['fields'][$name] = $item['fields'][$id]['value']
                                             ?? null;
                }

                $tickets[] = $item;
            }
        }

        return $tickets;
    }

    /**
     * Get a Deskpro client.
     */
    private function client(): DeskproClient
    {
        if (null === $this->client) {
            // https://github.com/deskpro/deskpro-api-client-php
            $client = new Client(['connect_timeout' => 2]);
            $this->client = new DeskproClient($this->options['deskpro_url'], $client);
            $authKey = explode(':', (string) $this->options['deskpro_api_code_key']);
            $this->client->setAuthKey(...$authKey);
        }

        return $this->client;
    }

    private function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired([
            'deskpro_url',
            'deskpro_api_code_key',
        ]);
    }
}

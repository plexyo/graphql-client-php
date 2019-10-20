<?php

namespace GraphQLClient;

use Symfony\Bundle\FrameworkBundle\Client as SymfonyClient;

class SymfonyWebTestGraphQLClient extends Client
{
    /**
     * @var SymfonyClient
     */
    private $symfonyClient;

    /**
     * @param SymfonyClient $client
     * @param string        $baseUrl
     */
    public function __construct(SymfonyClient $client, string $baseUrl)
    {
        parent::__construct($baseUrl);

        $this->symfonyClient = $client;
    }

    /**
     * @param array  $data
     * @param string $path
     *
     * @throws GraphQLException
     *
     * @return array
     */
    protected function postQuery(array $data, string $path): array
    {
        $this->symfonyClient->request('POST', $this->makeUrl($path), $data);

        $responseBody = json_decode($this->symfonyClient->getResponse()->getContent(), true);

        if (isset($responseBody['errors'])) {
            throw new GraphQLException(sprintf('Query failed with error %s', json_encode($responseBody['errors'])));
        }

        return $responseBody;
    }
}

<?php

namespace GraphQLClient;

use Laravel\Lumen\Testing\Concerns\MakesHttpRequests;
use Laravel\Lumen\Application;

class LumenTestGraphQLClient extends Client
{
    use MakesHttpRequests;

    /**
     * @var Application
     */
    private $app;

    /**
     * @param Application $app
     * @param string      $baseUrl
     */
    public function __construct(Application $app, string $baseUrl)
    {
        parent::__construct($baseUrl);

        $this->app = $app;
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
        $this->post($this->makeUrl($path), $data);

        if ($this->response->getStatusCode() >= 400) {
            throw new GraphQLException(sprintf(
                'Mutation failed with status code %d and error %s',
                $this->response->getStatusCode(),
                $this->response->getContent()
            ));
        }

        $responseBody = json_decode($this->response->getContent(), true);

        if (isset($responseBody['errors'])) {
            throw new GraphQLException(sprintf(
                'Mutation failed with error %s', json_encode($responseBody['errors'])
            ));
        }

        return $responseBody;
    }
}

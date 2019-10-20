<?php

namespace GraphQLClient;

use PHPUnit\Framework\Assert;

abstract class Client
{
    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var Variable[]
     */
    protected $variables;

    /**
     * @param string $baseUrl
     */
    public function __construct(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
        $this->variables = [];
    }

    /**
     * @param Query      $query
     * @param string     $path
     * @param array|null $multipart
     *
     * @return ResponseData
     */
    public function mutate(Query $query, string $path = '/', array $multipart = null): ResponseData
    {
        $response = $this->executeQuery($this->getMutationData($query), $path, $multipart);
        return new ResponseData($response['data'][$query->getName()]);
    }

    /**
     * @param array      $data
     * @param string     $path
     * @param array|null $multipart
     *
     * @return array
     */
    public function executeQuery(array $data, string $path = '/', array $multipart = null)
    {
        if (is_array($multipart)) {
            $data = array_merge(['operations' => json_encode($data)], $multipart);
        }

        return $this->postQuery($data, $path);
    }

    /**
     * @param array  $data
     * @param string $path
     *
     * @return array
     */
    abstract protected function postQuery(array $data, string $path): array;

    /**
     * @param Query $query
     *
     * @return array
     */
    private function getMutationData(Query $query): array
    {
        $queryBody = $this->getQueryString($query);
        $queryString = sprintf(
            'mutation %s { %s }',
            $query->getQueryHeader($this->variables),
            $queryBody
        );

        return [
            'query' => $queryString,
            'variables' => $this->getVariableContent($this->variables),
        ];
    }

    /**
     * @param Field $query
     *
     * @return string
     */
    private function getQueryString(Field $query): string
    {
        $fieldString = '';

        if ($query->getChildren()) {
            $fieldString .= '{';
            foreach ($query->getChildren() as $field) {
                $fieldString .= sprintf('%s', $this->getQueryString($field));
                $fieldString .= PHP_EOL;
            }
            $fieldString .= '}';
        }

        $paramString = '';
        if ($query instanceof Query) {
            $paramString = '('.$this->getParamString($query->getParams()).')';
        }

        return sprintf('%s%s %s', $query->getName(), $paramString, $fieldString);
    }

    /**
     * @param array $params
     *
     * @return string
     */
    private function getParamString(array $params): string
    {
        $result = '';

        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $result .= $key.' : ';
            }
            if (is_array($value)) {
                if ($this->hasStringKeys($value)) {
                    $result .= sprintf('{ %s } ', $this->getParamString($value));
                } else {
                    $result .= sprintf('[ %s ] ', $this->getParamString($value));
                }
            } elseif ($value instanceof Variable) {
                $result .= sprintf('$%s ', $value->getName());
                $this->variables[$value->getName()] = $value;
            } else {
                $result .= sprintf('%s ', json_encode($value));
            }

        }

        return $result;
    }

    /**
     * @param array $array
     *
     * @return bool
     */
    private function hasStringKeys(array $array): bool
    {
        return count(array_filter(array_keys($array), 'is_string')) > 0;
    }

    /**
     * @param Variable[] $variables
     *
     * @return array
     */
    private function getVariableContent(array $variables): array
    {
        $result = [];

        foreach ($variables as $variable) {
            $result[$variable->getName()] = $variable->getValue();
        }

        return $result;
    }

    /**
     * @param Query $query
     *
     * @return ResponseData
     */
    public function query(Query $query): ResponseData
    {
        $response = $this->executeQuery($this->getQueryData($query));

        return new ResponseData($response['data'][$query->getName()]);
    }

    /**
     * @param Query $query
     *
     * @return array
     */
    private function getQueryData(Query $query): array
    {
        $queryString = 'query { '.$this->getQueryString($query).' }';
        return [
            'query' => $queryString,
            'variables' => null,
        ];
    }

    /**
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @param array $fields
     * @param Query $query
     */
    public function assertGraphQlFields(array $fields, Query $query): void
    {
        foreach ($query->getChildren() as $field) {
            $this->assertFieldInArray($field, $fields);
        }
    }

    /**
     * @param Field $field
     * @param array $result
     */
    protected function assertFieldInArray(Field $field, array $result): void
    {
        if ($this->hasStringKeys($result)) {
            Assert::assertArrayHasKey($field->getName(), $result);
            if ($result[$field->getName()] !== null) {
                foreach ($field->getChildren() as $child) {
                    $this->assertFieldInArray($child, $result[$field->getName()]);
                }
            }
        } else {
            foreach ($result as $element) {
                $this->assertFieldInArray($field, $element);
            }
        }
    }

    /**
     * @param string $path
     *
     * @return string
     */
    protected function makeUrl(string $path = '/'): string
    {
        return rtrim($this->getBaseUrl(), '/').'/'.ltrim($path, '/');
    }
}

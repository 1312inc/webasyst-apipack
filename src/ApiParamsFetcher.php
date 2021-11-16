<?php

namespace ApiPack1312;

use ApiPack1312\Exception\ApiCastParamException;
use ApiPack1312\Exception\ApiException;
use ApiPack1312\Exception\ApiMissingParamException;
use ApiPack1312\Exception\ApiWrongParamException;
use DateTimeImmutable;
use Exception;
use waRequest;

class ApiParamsFetcher
{
    public const METHOD_GET    = 'GET';
    public const METHOD_POST   = 'POST';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_PUT    = 'PUT';

    private const WAREQUEST_GET  = 'get';
    private const WAREQUEST_POST = 'post';

    /**
     * @var array
     */
    protected $jsonParams = [];

    /**
     * @var ApiParamsCaster
     */
    private $caster;

    /**
     * @var waRequest
     */
    private $waRequest;

    public function __construct(waRequest $waRequest)
    {
        $this->caster = new ApiParamsCaster();

        // check for json request type
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strcmp($content_type, 'application/json') === 0) {
            $this->jsonParams = json_decode(file_get_contents('php://input'), true);
        }
        $this->waRequest = $waRequest;
    }

    /**
     * @param string      $name
     * @param bool        $required
     * @param string|null $type
     * @param string      $format
     *
     * @return array|DateTimeImmutable|float|int|string|null
     *
     * @throws ApiException
     * @throws ApiMissingParamException
     * @throws ApiWrongParamException
     */
    public function get(string $name, bool $required = false, ?string $type = null, string $format = '')
    {
        return $this->param(self::WAREQUEST_GET, $name, $required, $type, $format);
    }

    /**
     * @param string      $name
     * @param bool        $required
     * @param null|string $type
     * @param mixed       $format
     *
     * @return array|DateTimeImmutable|float|int|string|null
     *
     * @throws ApiException
     * @throws ApiMissingParamException
     * @throws ApiWrongParamException
     */
    public function post(string $name, bool $required = false, ?string $type = null, $format = null)
    {
        return $this->param(self::WAREQUEST_POST, $name, $required, $type, $format);
    }

    /**
     * @param string      $globalMethod
     * @param string      $name
     * @param bool        $required
     * @param null|string $type
     * @param mixed       $format
     *
     * @return array|DateTimeImmutable|float|int|string|null
     *
     * @throws ApiException
     * @throws ApiMissingParamException
     * @throws ApiWrongParamException
     */
    private function param(
        string $globalMethod,
        string $name,
        bool $required = false,
        ?string $type = null,
        string $format = ''
    ) {
        if ($this->jsonParams && array_key_exists($name, $this->jsonParams)) {
            $value = $this->jsonParams[$name];
        } else {
            try {
                $value = $this->fromWaRequest($globalMethod, $name, $required);
            } catch (ApiMissingParamException $exception) {
                throw $exception;
            } catch (Exception $exception) {
                throw new ApiException(sprintf('Api error getting param %s', $name), null, 500, $exception);
            }
        }

        try {
            return $value === null ? $value : $this->caster->cast($value, $type, $format);
        } catch (ApiCastParamException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            throw new ApiWrongParamException($name, $exception->getMessage(), $exception);
        }
    }

    /**
     * @throws ApiMissingParamException
     */
    private function fromWaRequest(string $globalMethod, $name, $required = false)
    {
        $v = $this->waRequest->{$globalMethod}($name);
        if ($required && $v === null) {
            throw new ApiMissingParamException($name);
        }

        return $v;
    }
}

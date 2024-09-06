<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A Symfony service to retrieve the version contained in the "Accept" field of the HTTP request.
 */
class VersioningService
{
    private RequestStack $requestStack;
    private string $defaultVersion;

    /**
     * Constructor allowing to retrieve the current request (to extract the "Accept" field from the header)
     * as well as the ParameterBagInterface to get the default version from the configuration file.
     *
     * @param RequestStack $requestStack
     * @param ParameterBagInterface $params
     */
    public function __construct(RequestStack $requestStack, ParameterBagInterface $params)
    {
        $this->requestStack = $requestStack;
        $this->defaultVersion = $params->get('default_api_version');
    }

    /**
     * Retrieve the version sent in the "Accept" header of the HTTP request.
     *
     * @return string The version number. By default, the returned version is the one defined in the services.yaml configuration file: "default_api_version".
     */
    public function getVersion(): string
    {  
        $version = $this->defaultVersion;

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            // If there is no current request, return the default version
            return $version;
        }

        $accept = $request->headers->get('Accept');
        if ($accept === null) {
            // If 'Accept' header is missing, return the default version
            return $version;
        }

        // Retrieve the version number from the Accept string:
        // example "application/json; test=bidule; version=2.0" => 2.0
        $headers = explode(';', $accept);

        foreach ($headers as $header) {
            $header = trim($header); // Trim any extra spaces
            if (strpos($header, 'version=') === 0) {
                $version = substr($header, strlen('version='));
                break;
            }
        }

        return $version;
    }
}

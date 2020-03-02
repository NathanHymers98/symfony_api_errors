<?php

namespace AppBundle\Api;
use Symfony\Component\HttpFoundation\Response;

/**
 * A wrapper for holding data to be used for a application/problem+json response
 */
class ApiProblem
{
    // Creating two const variables and setting their values to the error strings (type)
    const TYPE_VALIDATION_ERROR = 'validation_error';
    const TYPE_INVALID_REQUEST_BODY_FORMAT = 'invalid_body_format';

    private static $titles = array( // Using an associative array to map the constants made above to an error message (title)
        self::TYPE_VALIDATION_ERROR => 'There was a validation error',
        self::TYPE_INVALID_REQUEST_BODY_FORMAT => 'Invalid JSON format sent',
    );

    private $statusCode;

    private $type;

    private $title;

    private $extraData = array();

    public function __construct($statusCode, $type = null)
    {
        $this->statusCode = $statusCode;

        if ($type === null) {
            // no type? The default is about:blank and the title should
            // be the standard status code message
            $type = 'about:blank';
            $title = isset(Response::$statusTexts[$statusCode])
                ? Response::$statusTexts[$statusCode]
                : 'Unknown status code :(';
        } else {
            if (!isset(self::$titles[$type])) { // If the $type key in the array $titles is not set then throw a new exception with a message saying that there is no title for the error type
                throw new \InvalidArgumentException('No title for type '.$type);
            }

            $title = self::$titles[$type]; // If the $type key in the array is set, then assign it to the variable $title
        }

        $this->type = $type;
        $this->title = $title;
    }

    public function toArray()
    {
        return array_merge(
            $this->extraData,
            array(
                'status' => $this->statusCode,
                'type' => $this->type,
                'title' => $this->title,
            )
        );
    }

    public function set($name, $value)
    {
        $this->extraData[$name] = $value;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getTitle()
    {
        return $this->title;
    }
}

<?php

/**
 * @throws ErrorException
 */
function exceptions_error_handler($severity, $message, $filename, $lineno)
{
    echo '<pre>';
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}

set_error_handler('exceptions_error_handler');

class DotEnv
{
    private string $path;

    public function __construct(string $path)
    {
        try {
            if (!file_exists($path)) {
                throw new Exception(sprintf('%s file doesn\'t exist', $path));
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            die();
        }

        try {
            if (!is_readable($path)) {
                throw new Exception(sprintf('%s file is not readable', $path));
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            die();
        }

        $this->path = $path;
    }

    public function load(): void
    {
        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {

            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = str_replace(["'", '"'], '', trim($value));

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

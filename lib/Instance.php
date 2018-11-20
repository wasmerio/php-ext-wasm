<?php

declare(strict_types = 1);

namespace WASM;

use RuntimeException;

final class Instance
{
    private $filePath;
    private $wasmBinary;

    public function __construct(string $filePath)
    {
        if (false === file_exists($filePath)) {
            throw new RuntimeException("File path to WASM binary `$filePath` does not exist.");
        }

        $this->filePath = $filePath;
        $this->wasmBinary = wasm_read_binary($this->filePath);
        $this->wasmInstance = wasm_new_instance($this->filePath, $this->wasmBinary);
    }

    public function __call(string $name, array $arguments)
    {
        $signature = wasm_get_function_signature($this->wasmInstance, $name);

        if (null === $signature) {
            throw new InvocationException("Function `$name` does not exist.");
        }

        $number_of_expected_arguments = count($signature) - 1;
        $number_of_given_arguments = count($arguments);
        $diff = $number_of_expected_arguments - $number_of_given_arguments;

        if ($diff > 0) {
            throw new InvocationException(
                "Missing $diff argument(s) when calling `$name`: " .
                "Expect $number_of_expected_arguments arguments, " .
                "given $number_of_given_arguments."
            );
        } elseif ($diff < 0) {
            $diff = abs($diff);

            throw new InvocationException(
                "Given $diff extra argument(s) when calling `$name`: " .
                "Expect $number_of_expected_arguments arguments, " .
                "given $number_of_given_arguments."
            );
        }

        $argumentsBuilder = new ArgumentsBuilder();

        foreach ($arguments as $i => $argument) {
            $s = $i + 1;

            switch ($signature[$i]) {
                case SIGNATURE_TYPE_I32:
                    if (!is_int($argument)) {
                        throw new InvocationException("Argument #$s of `$name` must be a `i32` (integer).");
                    }

                    $argumentsBuilder->addI32($argument);

                    break;

                case SIGNATURE_TYPE_I64:
                    if (!is_int($argument)) {
                        throw new InvocationException("Argument #$s of `$name` must be a `i64` (integer).");
                    }

                    $argumentsBuilder->addI64($argument);

                    break;

                case SIGNATURE_TYPE_F32:
                    if (!is_float($argument)) {
                        throw new InvocationException("Argument #$s of `$name` must be a `f32` (float).");
                    }

                    $argumentsBuilder->addF32($argument);

                    break;

                case SIGNATURE_TYPE_F64:
                    if (!is_float($argument)) {
                        throw new InvocationException("Argument #$s of `$name` must be a `f64` (float).");
                    }

                    $argumentsBuilder->addF64($argument);

                    break;

                default:
                    throw new InvocationException("Unknown argument type `$signature[$i]` at position #$s of `$name`.");
            }
        }

        $result = wasm_invoke_function($this->wasmInstance, $name, $argumentsBuilder->intoResource());

        if (false === $result) {
            throw new InvocationException("Got an error when invoking `$name`.");
        }

        return $result;
    }
}

final class InvocationException extends RuntimeException {}
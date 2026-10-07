<?php

namespace MBLSolutions\AuditLogging\Support\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JsonException;

trait ShouldMaskSensitiveData
{

    public function maskSensitiveData($data, array $protected = [])
    {
        if (is_array($data)) {
            return $this->maskArrayData($data, $protected);
        }

        if ($data instanceof Collection) {
            return new Collection($this->maskArrayData($data->toArray(), $protected));
        }

        if ($this->isJson($data)) {
            return $this->maskJsonData($data, $protected);
        }

        return $data;
    }

    public function maskJsonData(string $data, array $protected): string
    {
        // Plain decode first: stripslashes breaks valid JSON with escaped quotes, which skipped masking.
        foreach ([$data, stripslashes($data)] as $candidate) {
            try {
                $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);

                if (! is_array($decoded)) {
                    return $data;
                }

                return json_encode($this->maskArrayData($decoded, $protected), JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {}
        }

        return $data;
    }

    public function maskArrayData(array $data, array $protected): array
    {
        $protected = array_map(fn ($key) => strtolower((string) $key), $protected);

        foreach ($data as $key => $datum) {
            if (is_array($datum)) {
                $data[$key] = $this->maskArrayData($datum, $protected);
            }
            // Case-insensitive, so "PIN" and "Pan" are masked too.
            if (in_array(strtolower((string) $key), $protected, true)) {
                $data[$key] = $this->mask($datum);
            }
        }

        return $data;
    }

    protected function mask($datum): string
    {
        if (is_string($datum)) {
            return Str::limit(str_repeat('*', strlen($datum)), 32);
        }

        return '****';
    }

    protected function isJson($data): bool
    {
        json_decode($data);

        return json_last_error() === JSON_ERROR_NONE;
    }

}
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
        try {
            $decoded = json_decode($data, true, config('audit-logging.max_loggable_length'), JSON_THROW_ON_ERROR);

            $sanitised = $this->maskArrayData($decoded, $protected);

            return json_encode($sanitised, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {}

        return $data;
    }

    public function maskArrayData(array $data, array $protected): array
    {
        foreach ($data as $key => $datum) {
            if (is_array($datum)) {
                $data[$key] = $this->maskArrayData($datum, $protected);
            }
            if (in_array($key, $protected, true)) {
                $data[$key] = $this->mask($datum);
            }
        }

        return $data;
    }

    protected function mask($datum): string
    {
        return Str::limit(str_repeat('*', strlen($datum)), 32);
    }

    protected function isJson($data): bool
    {
        json_decode($data);

        return json_last_error() === JSON_ERROR_NONE;
    }

}
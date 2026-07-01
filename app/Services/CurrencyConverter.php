<?php

namespace App\Services;

class CurrencyConverter
{
    public function defaultCurrency(): string
    {
        return config('finance.default_currency', 'LKR');
    }

    public function convertAmount($amount, ?string $sourceCurrency, ?string $targetCurrency = null): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $targetCurrency ??= $this->defaultCurrency();
        $sourceCurrency = strtoupper((string) ($sourceCurrency ?: $targetCurrency));
        $targetCurrency = strtoupper($targetCurrency);

        $numericAmount = (float) $amount;

        if ($sourceCurrency === $targetCurrency) {
            return number_format($numericAmount, 2, '.', '');
        }

        $sourceToLkr = $this->rateToLkr($sourceCurrency);
        $targetToLkr = $this->rateToLkr($targetCurrency);

        if ($sourceToLkr === null || $targetToLkr === null || $targetToLkr === 0.0) {
            return number_format($numericAmount, 2, '.', '');
        }

        $lkrAmount = $numericAmount * $sourceToLkr;
        $converted = $lkrAmount / $targetToLkr;

        return number_format(round($converted, 2), 2, '.', '');
    }

    public function convertPayload(mixed $value, ?string $targetCurrency = null): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $targetCurrency ??= $this->defaultCurrency();

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->convertPayload($item, $targetCurrency);
            }
        }

        if (array_key_exists('amount', $value) && array_key_exists('currency', $value)) {
            $value['amount'] = $this->convertAmount($value['amount'], $value['currency'], $targetCurrency);
            $value['currency'] = $targetCurrency;
        }

        foreach ($value as $key => $item) {
            if (!is_numeric($item)) {
                continue;
            }

            $currencyKey = $this->resolveCurrencyKey($key, $value);
            if ($currencyKey === null || !array_key_exists($currencyKey, $value)) {
                continue;
            }

            $value[$key] = $this->convertAmount($item, $value[$currencyKey], $targetCurrency);
            $value[$currencyKey] = $targetCurrency;
        }

        if (isset($value['computed']) && is_array($value['computed']) && array_key_exists('total_amount', $value)) {
            $value['computed']['grandTotal'] = (float) $value['total_amount'];
        }

        return $value;
    }

    private function resolveCurrencyKey(string $amountKey, array $payload): ?string
    {
        if ($amountKey === 'amount' && array_key_exists('currency', $payload)) {
            return 'currency';
        }

        $directKey = $amountKey . '_currency';
        if (array_key_exists($directKey, $payload)) {
            return $directKey;
        }

        if (str_ends_with($amountKey, '_amount')) {
            $baseKey = substr($amountKey, 0, -7) . '_currency';
            if (array_key_exists($baseKey, $payload)) {
                return $baseKey;
            }
        }

        return null;
    }

    private function rateToLkr(string $currency): ?float
    {
        $rates = config('finance.exchange_rates', []);
        $currency = strtoupper($currency);

        if (!isset($rates[$currency])) {
            return null;
        }

        return (float) $rates[$currency];
    }
}

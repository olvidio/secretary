<?php

declare(strict_types=1);

namespace src\shared\domain\value_objects;

use InvalidArgumentException;

final class Dinero
{
    private string $amount;

    public function __construct(string $amount)
    {
        $normalized = self::normalize($amount);
        if (!preg_match('/^-?\d+\.\d{2}$/', $normalized)) {
            throw new InvalidArgumentException('Importe no válido: ' . $amount);
        }
        $this->amount = $normalized;
    }

    public static function fromInput(string $raw): self
    {
        $raw = trim(str_replace([' ', "\u{00A0}"], '', $raw));
        if ($raw === '' || $raw === '.') {
            throw new InvalidArgumentException('Falta la cantidad');
        }
        if (str_contains($raw, ',')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $raw) === 1) {
            $raw = str_replace('.', '', $raw);
        }
        if (!is_numeric($raw)) {
            throw new InvalidArgumentException('La cantidad debe ser numérica');
        }

        return new self(number_format((float) $raw, 2, '.', ''));
    }

    public static function zero(): self
    {
        return new self('0.00');
    }

    /** Importe exacto en céntimos (D4): 1 € = 100 céntimos, sin pasar por float. */
    public static function fromCents(int $cents): self
    {
        return new self(bcdiv((string) $cents, '100', 2));
    }

    public function toCents(): int
    {
        return (int) bcmul($this->amount, '100', 0);
    }

    public function add(self $other): self
    {
        return new self(bcadd($this->amount, $other->amount, 2));
    }

    public function sub(self $other): self
    {
        return new self(bcsub($this->amount, $other->amount, 2));
    }

    public function mulRatio(string $numerador, string $denominador): self
    {
        if (bccomp($denominador, '0', 8) === 0) {
            return self::zero();
        }
        $prod = bcmul($this->amount, $numerador, 8);
        return new self(number_format((float) bcdiv($prod, $denominador, 8), 2, '.', ''));
    }

    public function divInt(int $n): self
    {
        if ($n === 0) {
            return self::zero();
        }
        return new self(number_format((float) bcdiv($this->amount, (string) $n, 8), 2, '.', ''));
    }

    public function neg(): self
    {
        return new self(bcmul($this->amount, '-1', 2));
    }

    public function compare(self $other): int
    {
        return bccomp($this->amount, $other->amount, 2);
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0.00', 2) === 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->amount, '0.00', 2) < 0;
    }

    public function toString(): string
    {
        return $this->amount;
    }

    public function formatEs(): string
    {
        return number_format((float) $this->amount, 2, ',', '.');
    }

    /** Al euro más cercano; a partir de 50 céntimos, se aleja del cero. */
    public function redondearEuros(): self
    {
        $negativo = $this->isNegative();
        $abs = $negativo ? $this->neg() : $this;
        $euros = bcdiv($abs->amount, '1', 0);
        $centimos = bcsub($abs->amount, $euros, 2);
        if (bccomp($centimos, '0.50', 2) >= 0) {
            $euros = bcadd($euros, '1', 0);
        }
        $redondeado = new self($euros . '.00');

        return $negativo && !$redondeado->isZero() ? $redondeado->neg() : $redondeado;
    }

    /** Euros enteros en formato es-ES (1.234), ya redondeados. */
    public function formatEnteroEs(): string
    {
        $entero = $this->redondearEuros();
        $negativo = $entero->isNegative();
        $euros = explode('.', $negativo ? $entero->neg()->toString() : $entero->toString())[0];
        $grupos = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $euros);

        return ($negativo ? '-' : '') . ($grupos ?? $euros);
    }

    private static function normalize(string $amount): string
    {
        $amount = str_replace(',', '.', trim($amount));
        if ($amount === '') {
            $amount = '0';
        }
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException('Importe no numérico');
        }

        return number_format((float) $amount, 2, '.', '');
    }
}

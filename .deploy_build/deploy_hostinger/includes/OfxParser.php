<?php

class OfxParser
{
    public function parseFile(string $filePath): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException('Arquivo OFX não encontrado ou sem permissão de leitura.');
        }

        $rawContent = file_get_contents($filePath);
        if ($rawContent === false) {
            throw new RuntimeException('Falha ao ler o conteúdo do arquivo OFX.');
        }

        $content = $this->normalizeEncoding($rawContent);
        if (!$this->looksLikeOfx($content)) {
            throw new RuntimeException('Arquivo inválido: conteúdo não reconhecido como OFX.');
        }

        $transactions = $this->extractTransactions($content);
        $meta = [
            'bank_id' => $this->extractSimpleTag($content, 'BANKID'),
            'account_id' => $this->extractSimpleTag($content, 'ACCTID'),
            'currency' => $this->extractSimpleTag($content, 'CURDEF'),
            'ledger_balance' => $this->extractNumericTag($content, 'BALAMT'),
        ];

        return [
            'transactions' => $transactions,
            'meta' => $meta,
        ];
    }

    private function looksLikeOfx(string $content): bool
    {
        return stripos($content, '<OFX>') !== false || stripos($content, '<STMTTRN>') !== false;
    }

    private function normalizeEncoding(string $content): string
    {
        $headerCharset = null;
        if (preg_match('/CHARSET:([^\r\n]+)/i', $content, $m)) {
            $headerCharset = strtoupper(trim($m[1]));
        }

        if ($headerCharset === '1252' || $headerCharset === 'WINDOWS-1252') {
            $converted = @iconv('WINDOWS-1252', 'UTF-8//IGNORE', $content);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        if (!mb_check_encoding($content, 'UTF-8')) {
            $converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $content);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        return $content;
    }

    private function extractTransactions(string $content): array
    {
        $blocks = $this->extractTransactionBlocks($content);
        $rows = [];

        foreach ($blocks as $block) {
            $amount = $this->extractNumericTag($block, 'TRNAMT');
            $dateRaw = $this->extractSimpleTag($block, 'DTPOSTED');
            $date = $this->normalizeDate($dateRaw);

            if ($amount === null || $date === null) {
                continue;
            }

            $name = $this->cleanText($this->extractSimpleTag($block, 'NAME') ?? '');
            $memo = $this->cleanText($this->extractSimpleTag($block, 'MEMO') ?? '');
            $description = $this->buildDescription($name, $memo);

            $rows[] = [
                'date' => $date,
                'description' => $description !== '' ? $description : 'Lancamento OFX',
                'amount' => (float)$amount,
                'type' => strtolower((string)$this->extractSimpleTag($block, 'TRNTYPE')),
                'fitid' => trim((string)$this->extractSimpleTag($block, 'FITID')),
                'name' => $name,
                'memo' => $memo,
            ];
        }

        return $rows;
    }

    private function extractTransactionBlocks(string $content): array
    {
        if (preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/is', $content, $matches)) {
            return $matches[1];
        }

        $parts = preg_split('/<STMTTRN>/i', $content);
        if (!is_array($parts) || count($parts) <= 1) {
            return [];
        }

        $blocks = [];
        foreach (array_slice($parts, 1) as $part) {
            $block = preg_split('/<\/STMTTRN>/i', $part, 2)[0] ?? '';
            if (trim($block) !== '') {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    private function extractSimpleTag(string $content, string $tag): ?string
    {
        $pattern = '/<' . preg_quote($tag, '/') . '>([^\r\n<]+)/i';
        if (preg_match($pattern, $content, $m)) {
            return trim((string)$m[1]);
        }
        return null;
    }

    private function extractNumericTag(string $content, string $tag): ?float
    {
        $value = $this->extractSimpleTag($content, $tag);
        if ($value === null) {
            return null;
        }

        $normalized = str_replace(',', '.', preg_replace('/[^0-9,\.\-]/', '', $value));
        if ($normalized === '' || !is_numeric($normalized)) {
            return null;
        }

        return (float)$normalized;
    }

    private function normalizeDate(?string $value): ?string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $raw, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        $time = strtotime($raw);
        if ($time === false) {
            return null;
        }

        return date('Y-m-d', $time);
    }

    private function cleanText(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', trim($text));
        if (!is_string($text)) {
            return '';
        }
        return $text;
    }

    private function buildDescription(string $name, string $memo): string
    {
        if ($name !== '' && $memo !== '' && mb_strtolower($name, 'UTF-8') !== mb_strtolower($memo, 'UTF-8')) {
            return $name . ' - ' . $memo;
        }

        return $name !== '' ? $name : $memo;
    }
}

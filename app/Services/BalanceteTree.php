<?php
declare(strict_types=1);

/**
 * Monta a estrutura visual diretamente das linhas do balancete.
 * A hierarquia vem da indentação do arquivo; nenhuma linha DRE fixa é usada.
 */
class BalanceteTree
{
    public function rowsForImport(int $importId): array
    {
        $stmt = db()->prepare(
            'SELECT id, line_number, account_code, account_description,
                    indentation_level, is_analytical, movement_value, movement_type
                    , raw_line
             FROM trial_balance_rows
             WHERE import_id = ?
             ORDER BY line_number'
        );
        $stmt->execute([$importId]);

        return $this->decorate($stmt->fetchAll());
    }

    public function rowsForImports(array $importIds): array
    {
        if (empty($importIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($importIds), '?'));
        $stmt = db()->prepare(
            "SELECT tbr.id, tbr.import_id, tbr.line_number, tbr.account_code,
                    tbr.account_description, tbr.indentation_level, tbr.is_analytical,
                    tbr.movement_value, tbr.movement_type,
                    tbr.raw_line,
                    i.year, i.month, bu.code AS unit_code, bu.name AS unit_name
             FROM trial_balance_rows tbr
             JOIN imports i ON i.id = tbr.import_id
             JOIN business_units bu ON bu.id = i.business_unit_id
             WHERE tbr.import_id IN ({$placeholders})
             ORDER BY i.year, i.month, bu.code, tbr.line_number"
        );
        $stmt->execute($importIds);

        return $this->decorate($stmt->fetchAll());
    }

    private function decorate(array $rows): array
    {
        $this->normalizeIndentationFromRawLines($rows);

        $nextIndentByIndex = [];
        $count = count($rows);

        for ($i = 0; $i < $count; $i++) {
            $nextIndentByIndex[$i] = $i + 1 < $count ? (int)$rows[$i + 1]['indentation_level'] : -1;
        }

        foreach ($rows as $i => &$row) {
            $indent = (int)$row['indentation_level'];
            $row['has_children'] = $nextIndentByIndex[$i] > $indent;
            $row['signed_movement'] = $this->signedMovement(
                (float)$row['movement_value'],
                (string)($row['movement_type'] ?? '')
            );
        }
        unset($row);

        return $rows;
    }

    private function normalizeIndentationFromRawLines(array &$rows): void
    {
        $minGapByImport = [];

        foreach ($rows as $row) {
            $gap = $this->descriptionGap((string)($row['raw_line'] ?? ''));
            if ($gap === null) {
                continue;
            }

            $importKey = (string)($row['import_id'] ?? 'single');
            $minGapByImport[$importKey] = min($minGapByImport[$importKey] ?? $gap, $gap);
        }

        foreach ($rows as &$row) {
            $gap = $this->descriptionGap((string)($row['raw_line'] ?? ''));
            if ($gap === null) {
                continue;
            }

            $importKey = (string)($row['import_id'] ?? 'single');
            $baseGap = max(5, $minGapByImport[$importKey] ?? 5);
            $row['indentation_level'] = max(0, (int)floor(($gap - $baseGap) / 2));
        }
        unset($row);
    }

    private function descriptionGap(string $rawLine): ?int
    {
        if (preg_match('/^\s*\d{1,6}(\s+)/', $rawLine, $matches)) {
            return strlen($matches[1]);
        }

        return null;
    }

    private function signedMovement(float $value, string $type): float
    {
        if ($type === 'DB') {
            return -abs($value);
        }
        if ($type === 'CR') {
            return abs($value);
        }
        return $value;
    }
}

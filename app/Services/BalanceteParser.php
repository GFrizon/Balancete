<?php
declare(strict_types=1);

/**
 * BalanceteParser
 *
 * Lê um arquivo TXT, RTF ou DOC (texto monoespaçado) de balancete
 * e extrai cabeçalho + linhas de dados.
 *
 * REGRA CRÍTICA: Usa SOMENTE a coluna "Movimento".
 * Débito e Crédito são extraídos apenas para auditoria.
 */
class BalanceteParser
{
    // -------------------------------------------------------
    // Ponto de entrada
    // -------------------------------------------------------

    public function parse(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return $this->errorResult("Arquivo não encontrado: {$filePath}");
        }

        $raw = file_get_contents($filePath);
        if ($raw === false) {
            return $this->errorResult("Não foi possível ler o arquivo.");
        }

        // Detecta RTF
        if (str_starts_with(ltrim($raw), '{\\rtf')) {
            $text = $this->stripRtf($raw);
        } else {
            $text = $raw;
        }

        // Normaliza quebras de linha
        $text  = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);

        $header = $this->parseHeader($lines);
        $rows   = $this->parseRows($lines);

        return [
            'success' => true,
            'header'  => $header,
            'rows'    => $rows,
            'errors'  => [],
        ];
    }

    // -------------------------------------------------------
    // Strip RTF → texto plano UTF-8
    // -------------------------------------------------------

    private function stripRtf(string $rtf): string
    {
        // 1. Converter entidades hexadecimais RTF \'XX (ISO-8859-1) para UTF-8
        $text = preg_replace_callback(
            "/\\\\'([0-9a-fA-F]{2})/",
            function (array $m): string {
                return mb_convert_encoding(chr(hexdec($m[1])), 'UTF-8', 'ISO-8859-1');
            },
            $rtf
        );

        // 2. Substituir \par e \page por newlines
        $text = preg_replace('/\\\\(par|page)\b\s*/i', "\n", $text);

        // 3. Remover grupos ignorados: {\*\...}
        $text = preg_replace('/\{\\\\\*[^}]*\}/s', '', $text);

        // 4. Remover comandos de controle RTF (\word ou \word-N)
        $text = preg_replace('/\\\\[a-z]+[-]?\d*\s*/i', '', $text);

        // 5. Remover chaves restantes
        $text = str_replace(['{', '}'], '', $text);

        // 6. Limpar espaços extras nas linhas (preservar indentação inicial)
        $lines = explode("\n", $text);
        $clean = [];
        foreach ($lines as $line) {
            // Preservar leading spaces mas limpar trailing
            $clean[] = rtrim($line);
        }

        return implode("\n", $clean);
    }

    // -------------------------------------------------------
    // Parse do cabeçalho
    // -------------------------------------------------------

    private function parseHeader(array $lines): array
    {
        $header = [
            'empresa_nome'   => '',
            'cnpj'           => '',
            'unidade_codigo' => '',
            'unidade_nome'   => '',
            'periodo_mes'    => 0,
            'periodo_ano'    => 0,
            'livro'          => '',
            'folha'          => '',
        ];

        foreach ($lines as $line) {
            $line = trim($line);

            // Linha 1: Empresa + CNPJ [+ Livro + Folha]
            if ($header['empresa_nome'] === '' && preg_match(
                '/^(.+?)\s*[-–]\s*CNPJ[:\s]+([0-9]{2}\.[0-9]{3}\.[0-9]{3}\/[0-9]{4}-[0-9]{2})/i',
                $line,
                $m
            )) {
                $header['empresa_nome'] = trim($m[1]);
                $header['cnpj']         = trim($m[2]);

                // Livro e Folha (opcionais na mesma linha)
                if (preg_match('/Livro[:\s]+(\S+)/i', $line, $lm)) {
                    $header['livro'] = trim($lm[1]);
                }
                if (preg_match('/Folha[:\s]+(\S+)/i', $line, $fm)) {
                    $header['folha'] = trim($fm[1]);
                }
                continue;
            }

            // Linha 2: Unidade + Período
            if ($header['unidade_codigo'] === '' && preg_match(
                '/Unidade\s+de\s+Neg[oó]cio[:\s]+(\d+)\s*[-–]\s*(.+?)(?:\s{2,}|$)/iu',
                $line,
                $m
            )) {
                $header['unidade_codigo'] = trim($m[1]);
                $header['unidade_nome']   = trim($m[2]);
            }

            if ($header['periodo_mes'] === 0 && preg_match(
                '/Per[íi]odo[:\s]+([A-ZÁÉÍÓÚÂÊÔÃÕÇ]+)\s+DE\s+(\d{4})/iu',
                $line,
                $m
            )) {
                $header['periodo_mes'] = $this->parseMes(strtoupper(trim($m[1])));
                $header['periodo_ano'] = (int)$m[2];
            }

            // Se já temos tudo, parar
            if ($header['empresa_nome'] !== ''
                && $header['unidade_codigo'] !== ''
                && $header['periodo_mes'] !== 0) {
                break;
            }
        }

        return $header;
    }

    // -------------------------------------------------------
    // Parse das linhas de dados
    // -------------------------------------------------------

    private function parseRows(array $lines): array
    {
        $rows       = [];
        $lineNumber = 0;

        /**
         * Regex para linha de dados:
         *   ^(\s{1,12})    — indentação leading
         *   (\d{1,6})      — código numérico
         *   (\s+)          — separador/indentação visual antes da descrição
         *   (.+?)          — descrição (lazy)
         *   \s{2,}         — múltiplos espaços
         *   (valor)\s+     — débito
         *   (valor)\s+     — crédito
         *   (valor)        — movimento
         *   (DB|CR)?       — tipo movimento
         */
        $regex = '/^(\s{0,15})(\d{1,6})(\s{1,20})(.+?)\s{2,}(\d{1,3}(?:\.\d{3})*,\d{2}|0,00)\s+(\d{1,3}(?:\.\d{3})*,\d{2}|0,00)\s{1,}(\d{1,3}(?:\.\d{3})*,\d{2}|0,00)(DB|CR)?\s*$/';

        // Linhas a ignorar (cabeçalhos repetidos, separadores)
        $skipPatterns = [
            '/^-{10,}/',                        // ----...----
            '/C[oó]digo\s+Descri/i',            // cabeçalho da tabela
            '/BALANCETE\s+SINTETICO/i',
            '/GESTAO\s+CONTABIL/i',
            '/^\s*$/',                           // linha vazia
        ];

        foreach ($lines as $rawLine) {
            $lineNumber++;

            // Verificar se a linha deve ser ignorada
            $skip = false;
            foreach ($skipPatterns as $pattern) {
                if (preg_match($pattern, $rawLine)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            if (!preg_match($regex, $rawLine, $m)) {
                continue;
            }

            $indent      = strlen($m[3]);
            $code        = $m[2];
            $description = $this->cleanDescription(trim($m[4]));
            $debit       = $this->parseValue($m[5]);
            $credit      = $this->parseValue($m[6]);
            $movement    = $this->parseValue($m[7]);
            $mvType      = $m[8] ?? '';

            // Detecta sub-conta analítica: descrição começa com padrão "NNN texto"
            // onde NNN é código de unidade (ex: "001 Receita Mercadoria Industria")
            $isAnalytical = (int)preg_match('/^\d{3}\s+\S/', $description);

            $rows[] = [
                'line_number'         => $lineNumber,
                'account_code'        => $code,
                'account_description' => $description,
                'indentation_level'   => $this->indentationFromCodeGap($indent),
                'is_analytical'       => $isAnalytical,
                'movement_value'      => $movement,
                'movement_type'       => $mvType,
                'debit'               => $debit,
                'credit'              => $credit,
                'raw_line'            => rtrim($rawLine),
            ];
        }

        return $rows;
    }

    // -------------------------------------------------------
    // Conversão de valor BR → float
    // -------------------------------------------------------

    private function parseValue(string $value): float
    {
        // "11.001.024,12" → 11001024.12
        $clean = str_replace('.', '', $value); // remove separadores de milhar
        $clean = str_replace(',', '.', $clean); // vírgula → ponto decimal
        return (float)$clean;
    }

    private function cleanDescription(string $description): string
    {
        $description = trim(preg_replace('/\s+/', ' ', $description) ?? $description);
        $money = '\d{1,3}(?:\.\d{3})*,\d{2}';

        do {
            $previous = $description;
            $description = preg_replace('/\s+' . $money . '(?:DB|CR)?$/u', '', $description) ?? $description;
            $description = trim($description);
        } while ($description !== $previous);

        return $description;
    }

    private function indentationFromCodeGap(int $gap): int
    {
        return max(0, (int)floor(($gap - 5) / 2));
    }

    // -------------------------------------------------------
    // Mês abreviado PT → número
    // -------------------------------------------------------

    private function parseMes(string $mes): int
    {
        return MONTHS_PT_SHORT[$mes] ?? 0;
    }

    // -------------------------------------------------------
    // Utilitário de erro
    // -------------------------------------------------------

    private function errorResult(string $message): array
    {
        return [
            'success' => false,
            'header'  => [],
            'rows'    => [],
            'errors'  => [$message],
        ];
    }
}

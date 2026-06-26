-- Remove a estrutura antiga baseada em DRE/mapeamentos da planilha.
-- O app agora usa trial_balance_rows diretamente, respeitando a hierarquia do balancete importado.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS manual_adjustments;
DROP TABLE IF EXISTS generated_dre_values;
DROP TABLE IF EXISTS dre_mappings;
DROP TABLE IF EXISTS dre_lines;

SET FOREIGN_KEY_CHECKS = 1;

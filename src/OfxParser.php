<?php
// src/OfxParser.php

class OfxParser {
    
    /**
     * Parse un fichier OFX et retourne un tableau de transactions structuré.
     * Compatible avec les fichiers contenant plusieurs comptes (blocs STMTRS multiples).
     */
    public function parse(string $filePath): array {
        if (!file_exists($filePath)) {
            throw new Exception("Fichier introuvable");
        }

        $content = file_get_contents($filePath);
        
        // Gestion de l'encodage (souvent Windows-1252 chez CA, conversion en UTF-8)
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'WINDOWS-1252'], true);
        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding ?: 'WINDOWS-1252');
        }

        $transactions = [];

        // 1. Découpage par bloc de compte (<STMTRS> ... </STMTRS>)
        // Chaque bloc correspond à un relevé de compte spécifique
        preg_match_all('/<STMTRS>(.*?)<\/STMTRS>/s', $content, $accountBlocks);

        // Fallback : Si pas de bloc STMTRS détecté, on traite tout le fichier comme un seul bloc
        if (empty($accountBlocks[1])) {
            $accountBlocks[1] = [$content];
        }

        foreach ($accountBlocks[1] as $block) {
            // A. Extraction du numéro de compte POUR CE BLOC
            preg_match('/<ACCTID>(.*?)(\n|<)/', $block, $matchesAcct);
            $accountNumber = isset($matchesAcct[1]) ? trim($matchesAcct[1]) : 'UNKNOWN';

            // B. Extraction des transactions de CE BLOC UNIQUEMENT
            preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/s', $block, $matchesTrn);

            foreach ($matchesTrn[1] as $trnBlock) {
                $tx = [
                    'account_number' => $accountNumber, // On assigne le bon compte trouvé plus haut
                    'fitid' => $this->extractTag('FITID', $trnBlock),
                    'amount' => $this->extractAmount($trnBlock),
                    'date' => $this->extractDate($trnBlock),
                    'label' => $this->extractLabel($trnBlock),
                ];

                // On ne garde que les transactions valides (avec ID et Date)
                if ($tx['fitid'] && $tx['date']) {
                    $transactions[] = $tx;
                }
            }
        }

        return $transactions;
    }

    private function extractTag(string $tag, string $block): ?string {
        if (preg_match('/<' . $tag . '>(.*?)(\n|<)/', $block, $match)) {
            return trim($match[1]);
        }
        return null;
    }

    private function extractAmount(string $block): float {
        $val = $this->extractTag('TRNAMT', $block);
        if (!$val) return 0.0;
        return (float) str_replace(',', '.', $val);
    }

    private function extractDate(string $block): ?string {
        $val = $this->extractTag('DTPOSTED', $block);
        if (!$val) return null;
        // Format OFX standard: YYYYMMDD...
        $dateStr = substr($val, 0, 8);
        $date = DateTime::createFromFormat('Ymd', $dateStr);
        return $date ? $date->format('Y-m-d') : null;
    }

    private function extractLabel(string $block): string {
        $name = $this->extractTag('NAME', $block) ?? '';
        $memo = $this->extractTag('MEMO', $block) ?? '';
        return trim($name . ' ' . $memo);
    }
}
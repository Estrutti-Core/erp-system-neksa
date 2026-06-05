<?php

namespace App\Enums;

enum FiscalOrigin: int
{
    case Nacional = 0;
    case EstrangeiraDireta = 1;
    case EstrangeiraAdquiridaInterno = 2;
    case NacionalImportacaoMedia = 3;
    case NacionalPPB = 4;
    case NacionalImportacaoBaixa = 5;
    case EstrangeiraDiretaSemSimilar = 6;
    case EstrangeiraAdquiridaInternoSemSimilar = 7;
    case NacionalImportacaoAlta = 8;

    public function label(): string
    {
        return match($this) {
            self::Nacional => '0 - Nacional',
            self::EstrangeiraDireta => '1 - Estrangeira - Importação direta',
            self::EstrangeiraAdquiridaInterno => '2 - Estrangeira - Adquirida no mercado interno',
            self::NacionalImportacaoMedia => '3 - Nacional - Conteúdo Importação de 40% a 70%',
            self::NacionalPPB => '4 - Nacional - Processos Produtivos Básicos (PPB)',
            self::NacionalImportacaoBaixa => '5 - Nacional - Conteúdo Importação <= 40%',
            self::EstrangeiraDiretaSemSimilar => '6 - Estrangeira - Importação direta sem similar nacional',
            self::EstrangeiraAdquiridaInternoSemSimilar => '7 - Estrangeira - Adquirida mercado interno sem similar nacional',
            self::NacionalImportacaoAlta => '8 - Nacional - Conteúdo Importação > 70%',
        };
    }
}

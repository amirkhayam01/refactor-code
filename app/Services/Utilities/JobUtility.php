<?php

namespace DTApi\Services\Utilities;



class JobUtility
{

    public function getTranslatorType($userMeta)
    {
        $translatorTypes = [
            'professional' => 'paid',
            'rwstranslator' => 'rws',
            'volunteer' => 'unpaid',
        ];

        return $translatorTypes[$userMeta->translator_type] ?? 'unpaid';
    }

    
}

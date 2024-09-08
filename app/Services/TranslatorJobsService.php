<?php

namespace DTApi\Services;

use DTApi\Repository\TranslatorJobsRepository;

class TranslatorJobsService
{

    public $translatorJobsRepository;
    public function __construct(TranslatorJobsRepository $translatorJobsRepository)
    {
        $this->translatorJobsRepository = $translatorJobsRepository;
    }

    public function getTranslatorJobIdsByEmail($emails)
    {
        return $this->translatorJobsRepository->getTranslatorJobIdsByEmail($emails);
    }


    public function getTranslatorJobIdsByUserIDs($emails)
    {
        return $this->translatorJobsRepository->getTranslatorJobIdsByUserIDs($emails);
    }

}

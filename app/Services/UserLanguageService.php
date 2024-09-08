<?php

namespace DTApi\Services;

use DTApi\Repository\UserRepository;
use DTApi\Repository\UserLanguageRepository;

class UserLanguageService
{

    public $userLanguageRepository;
    public function __construct(UserLanguageRepository $userLanguageRepository)
    {
        $this->userLanguageRepository = $userLanguageRepository;
    }

    public function getUserLanguagesByUserId($userId)
    {
        return $this->userLanguageRepository->getUserLanguagesByUserId($userId);
    }

}
<?php

namespace DTApi\Repository;

use DTApi\Repository\BaseRepository;


class UserLanguageRepository extends BaseRepository
{

    public function getUserLanguagesByUserId($userId)
    {
        return UserLanguages::where('user_id', '=', $userId)->get();
    }
}

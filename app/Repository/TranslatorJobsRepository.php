[<?php

namespace DTApi\Repository;

use DTApi\Repository\BaseRepository;

class TranslatorJobsRepository extends BaseRepository
{

    public function getTranslatorJobIdsByEmail($emails)
    {
        return DB::table('translator_job_rel')
            ->join('users', 'users.id', '=', 'translator_job_rel.user_id')
            ->whereIn('users.email', $emails)
            ->whereNull('translator_job_rel.cancel_at')
            ->pluck('translator_job_rel.job_id');
    }


    public function getTranslatorJobIdsByUserIDs($userIds)
    {
        return DB::table('translator_job_rel')
            ->whereNull('cancel_at')
            ->whereIn('user_id', $userIds)
            ->pluck('job_id');
    }
}

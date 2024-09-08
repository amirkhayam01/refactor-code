<?php

namespace DTApi\Repository;

use DTApi\Repository\BaseRepository;

class DistanceRepository extends BaseRepository
{

    public function updateByJobId($jobid, $data)
    {
        Distance::where('job_id', $jobid)
            ->update($data);
    }
}

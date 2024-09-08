<?php

namespace DTApi\Services;

use DTApi\Repository\DistanceRepository;

class DistanceService
{

    public $distanceRepository;
    public function __construct(DistanceRepository $distanceRepository)
    {
        $this->distanceRepository = $distanceRepository;
    }

    public function updateByJobId($jobId, $data)
    {
        return $this->distanceRepository->updateByJobId($jobId, $data);
    }


}

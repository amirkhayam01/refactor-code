<?php

namespace DTApi\Services;

use DTApi\Repository\JobRepository;

class JobService
{


    public $userService;
    public $jobRepository;
    public $translatorJobsService;
    public $userLanguageService;
    public $notificationService;

    public function __construct(
        JobRepository $jobRepository,
        UserService $userService,
        TranslatorJobsService $translatorJobsService,
        UserLanguageService $userLanguageService,
        NotificationService $notificationService
    ) {
        $this->userService = $userService;
        $this->jobRepository = $jobRepository;
        $this->translatorJobsService = $translatorJobsService;
        $this->userLanguageService = $userLanguageService;
        $this->notificationService = $notificationService;
    }

    public function getUsersJobs($user_id)
    {
        $cuser = $this->userService->findUserByID($user_id);
        $usertype = '';
        $emergencyJobs = [];
        $noramlJobs = [];


        if ($this->userService->isCustomer($cuser)) {
            $jobs = $this->userService->getUserJobs($cuser);
            $usertype = 'customer';
        } elseif ($this->userService->isTranslator($cuser)) {

            $jobs = $this->jobRepository->getTranslatorJobs($cuser->id);
            $usertype = 'translator';
        }


        if ($jobs) {
            foreach ($jobs as $jobitem) {
                if ($jobitem->immediate == 'yes') {
                    $emergencyJobs[] = $jobitem;
                } else {
                    $noramlJobs[] = $jobitem;
                }
            }
            $noramlJobs = collect($noramlJobs)->each(function ($item, $key) use ($user_id) {
                $item['usercheck'] = Job::checkParticularJob($user_id, $item);
            })->sortBy('due')->all();
        }

        return ['emergencyJobs' => $emergencyJobs, 'noramlJobs' => $noramlJobs, 'cuser' => $cuser, 'usertype' => $usertype];
    }


    public function getAllJobs($request, $limit = null)
    {
        $requestData = $request->all();
        $cuser = $request->__authenticatedUser;
        $query = $this->jobRepository->query();


        $query = $this->applyCommonFilters($query, $requestData);

        if ($this->userService->isSuperAdmin($cuser)) {
            $query = $this->applySuperAdminFilters($query, $requestData);
        } else {

            $query = $this->applyOtherFilters($query, $requestData, $cuser);
        }

        $query =  $query->orderBy('created_at', 'desc')->with('user', 'language', 'feedback.user', 'translatorJobRel.user', 'distance');

        return $limit === 'all' ? $query->get() : $query->paginate(15);
    }


    public function findJobByID($jobId)
    {
        return $this->jobRepository->find($jobId);
    }

    public function update($jobId, $jobData)
    {
        return $this->jobRepository->update($jobId, $jobData);
    }

    private function applySuperAdminFilters($query, $requestData)
    {
        if (!empty($requestData['feedback']) && $requestData['feedback'] != 'false') {
            $query = $this->jobRepository->filterJobsByFeedback($query);
            if (!empty($requestData['count']) && $requestData['count'] != 'false') {
                return ['count' => $query->count()];
            }
        }

        if (!empty($requestData['expired_at'])) {
            $query = $this->jobRepository->filterJobsByExpiredDate($query, $requestData['expired_at']);
        }

        if (!empty($requestData['will_expire_at'])) {
            $query = $this->jobRepository->filterJobsByExpiryDate($query, $requestData['will_expire_at']);
        }

        if (!empty($requestData['translator_email'])) {
            $userIds = $this->userService->getUserIdsByEmail($requestData['translator_email']);
            if ($userIds) {
                $jobIDs = $this->translatorJobsService->getTranslatorJobIdsByUserIDs($userIds->toArray());
                $query = $this->jobRepository->findJobsByIDs($query, $jobIDs->toArray());
            }
        }

        if (!empty($requestData['physical'])) {
            $query = $this->jobRepository->filterByPhysicalType($query, $requestData['physical']);
        }

        if (!empty($requestData['phone'])) {
            $query = $this->jobRepository->filterByPhone($query, $requestData);
        }

        if (!empty($requestData['flagged'])) {
            $query = $this->jobRepository->filterByFlagged($query, $requestData['flagged']);
        }

        if (!empty($requestData['distance']) && $requestData['distance'] == 'empty') {
            $query = $this->jobRepository->filterByEmptyDistance($query);
        }

        if (!empty($requestData['salary']) && $requestData['salary'] == 'yes') {
            $query = $this->jobRepository->filterBySalary($query);
        }

        if (!empty($requestData['consumer_type'])) {
            $query = $this->jobRepository->filterByConsumerType($query, $requestData['consumer_type']);
        }

        if (!empty($requestData['booking_type'])) {
            $query = $this->jobRepository->filterByBookingType($query, $requestData['booking_type']);
        }

        return $query;
    }



    private function applyCommonFilters($query, $requestData)
    {
        if (!empty($requestData['id'])) {
            $query = $this->jobRepository->findJobsByIDs($query, $requestData['id']);
        }

        if (!empty($requestData['lang'])) {
            $query = $this->jobRepository->filterJobsByLang($query, $requestData['lang']);
        }

        if (!empty($requestData['status'])) {
            $query = $this->jobRepository->filterJobsByStatus($query, $requestData['status']);
        }

        if (!empty($requestData['filter_timetype'])) {
            $from = $requestData['from'] ?? '';
            $to = $requestData['to'] ?? '';
            $column = $requestData['filter_timetype'] == 'created' ? 'created_at' : ($requestData['filter_timetype'] == 'due' ? 'due' : '');

            if ($column) {
                $query->whereBetween($column, [$from, $to]);
                $query->orderBy($column, 'desc');
            }
        }

        if (!empty($requestData['job_type'])) {
            $query = $this->jobRepository->filterByJobType($query, $requestData['job_type']);
        }

        if (!empty($requestData['customer_email'])) {
            $userIds = $this->userService->getUserIdsByEmail($requestData['customer_email']);
            if (!empty($userIds)) {
                $query = $this->jobRepository->findJobsByUserIDs($query, $userIds->toArray());
            }
        }

        return $query;
    }



    private function applyOtherFilters($query, $requestData, $cuser)
    {

        $consumer_type = $cuser->consumer_type;

        if ($consumer_type == 'RWS') {
            $query->where('job_type', '=', 'rws');
        } else {
            $query->where('job_type', '=', 'unpaid');
        }


        if (!empty($requestData['customer_email'])) {
            $user = $this->userService->findUserByEmail($requestData['customer_email']);

            if ($user) {
                $query->where('user_id', '=', $user->id);
            }
        }


        if (!empty($requestData['filter_timetype'])) {
            $query = $this->jobRepository->filterByTime($query, $requestData);
        }

        return $query;
    }


    public function getTranslatorJobsHistoric($userId, $pagenum)
    {
        return $this->jobRepository->getTranslatorJobsHistoric($userId, 'historic', $pagenum);
    }

    public function getAssignedTranslator($job)
    {
        return Job::getJobsAssignedTranslatorDetail($job);
    }


    public function markJobPendingAndReassign($job, $translator)
    {
        $job->status = 'pending';
        $job->created_at = now();
        $job->will_expire_at = TeHelper::willExpireAt($job->due, now());
        $this->updateJobStatus($job);

        Job::deleteTranslatorJobRel($translator->id, $job->id);
    }


    public function getJobCustomer($job)
    {
        return $this->jobRepository->getJobCustomer($job);
    }


    /**
     * @param $data
     * @param $user
     */
    public function acceptJob($data, $user)
    {
        $jobId = $data['job_id'];
        $job = $this->jobRepository->findOrFail($jobId);

        if (!$this->isTranslatorAlreadyBooked($job, $user)) {
            $this->assignJobToTranslator($job, $user);
            $this->notificationService->notifyUser($job, $user);

            // Get updated job list
            $jobs = $this->getPotentialJobs($user);

            return [
                'list' => json_encode(['jobs' => $jobs, 'job' => $job], true),
                'status' => 'success'
            ];
        }

        return [
            'status' => 'fail',
            'message' => 'Du har redan en bokning den tiden! Bokningen är inte accepterad.'
        ];
    }

    public function isTranslatorAlreadyBooked($job, $user)
    {
        return $this->jobRepository->isTranslatorAlreadyBooked($job->id, $user->id, $job->due) && $job->status == 'pending';
    }

    private function assignJobToTranslator($job, $user)
    {
        if (Job::insertTranslatorJobRel($user->id, $job->id)) {
            $job->status = 'assigned';
            $job->save();
        }
    }


    public function assignTranslatorToJob($translator_id, $job_id)
    {
        return Job::insertTranslatorJobRel($translator_id, $job_id);
    }


    public function updateJobStatus($job, $status)
    {
        $data = [
            "status" => $status
        ];
        $this->jobRepository->update($job->id, $data);
    }







    /*Function to get the potential jobs for paid,rws,unpaid translators*/
    public function getPotentialJobs($cuser)
    {
        $translatorType = $this->getTranslatorType($cuser->userMeta);
        $languages = $this->userLanguageService->getUserLanguagesByUserId($cuser);
        $userLanguages = collect($languages)->pluck('lang_id')->all();

        $jobIds = $this->fetchJobs($cuser->id, $translatorType, $userLanguages, $cuser->userMeta);

        return $this->filterJobs($jobIds, $cuser->id);
    }

    private function getTranslatorType($userMeta)
    {
        $translatorTypes = [
            'professional' => 'paid',
            'rwstranslator' => 'rws',
            'volunteer' => 'unpaid',
        ];

        return $translatorTypes[$userMeta->translator_type] ?? 'unpaid';
    }

    private function fetchJobs($userId, $jobType, $userLanguages, $userMeta)
    {
        return Job::getJobs(
            $userId,
            $jobType,
            'pending',
            $userLanguages,
            $userMeta->gender,
            $userMeta->translator_level
        );
    }

    private function filterJobs($jobIds, $currentUserId)
    {
        foreach ($jobIds as $k => $job) {
            $job->specific_job = Job::assignedToPaticularTranslator($currentUserId, $job->id);
            $job->check_particular_job = Job::checkParticularJob($currentUserId, $job);
            $checkTown = Job::checkTowns($job->user_id, $currentUserId);

            if ($this->shouldRemoveJob($job, $currentUserId, $checkTown)) {
                unset($jobIds[$k]);
            }
        }

        return $jobIds;
    }

    private function shouldRemoveJob($job, $currentUserId, $checkTown)
    {
        if ($job->specific_job == 'SpecificJob' && $job->check_particular_job == 'userCanNotAcceptJob') {
            return true;
        }

        if (($job->customer_phone_type == 'no' || $job->customer_phone_type == '') &&
            $job->customer_physical_type == 'yes' &&
            !$checkTown
        ) {
            return true;
        }

        return false;
    }


    public function jobToData($job)
    {

        $data = array();            // save job's information to data for sending Push
        $data['job_id'] = $job->id;
        $data['from_language_id'] = $job->from_language_id;
        $data['immediate'] = $job->immediate;
        $data['duration'] = $job->duration;
        $data['status'] = $job->status;
        $data['gender'] = $job->gender;
        $data['certified'] = $job->certified;
        $data['due'] = $job->due;
        $data['job_type'] = $job->job_type;
        $data['customer_phone_type'] = $job->customer_phone_type;
        $data['customer_physical_type'] = $job->customer_physical_type;
        $data['customer_town'] = $job->town;
        $data['customer_type'] = $job->user->userMeta->customer_type;

        $due_Date = explode(" ", $job->due);
        $due_date = $due_Date[0];
        $due_time = $due_Date[1];

        $data['due_date'] = $due_date;
        $data['due_time'] = $due_time;

        $data['job_for'] = array();
        if ($job->gender != null) {
            if ($job->gender == 'male') {
                $data['job_for'][] = 'Man';
            } else if ($job->gender == 'female') {
                $data['job_for'][] = 'Kvinna';
            }
        }
        if ($job->certified != null) {
            if ($job->certified == 'both') {
                $data['job_for'][] = 'Godkänd tolk';
                $data['job_for'][] = 'Auktoriserad';
            } else if ($job->certified == 'yes') {
                $data['job_for'][] = 'Auktoriserad';
            } else if ($job->certified == 'n_health') {
                $data['job_for'][] = 'Sjukvårdstolk';
            } else if ($job->certified == 'law' || $job->certified == 'n_law') {
                $data['job_for'][] = 'Rätttstolk';
            } else {
                $data['job_for'][] = $job->certified;
            }
        }

        return $data;
    }
}

<?php

namespace DTApi\Services;

use DTApi\Repository\JobRepository;
use DTApi\Services\Utilities\JobUtility;

class JobService
{


    public $userService;
    public $jobRepository;
    public $translatorJobsService;
    public $userLanguageService;
    public $notificationService;
    public $jobUtility;

    public function __construct(
        JobRepository $jobRepository,
        UserService $userService,
        TranslatorJobsService $translatorJobsService,
        UserLanguageService $userLanguageService,
        NotificationService $notificationService,
        JobUtility $jobUtility
    ) {
        $this->userService = $userService;
        $this->jobRepository = $jobRepository;
        $this->translatorJobsService = $translatorJobsService;
        $this->userLanguageService = $userLanguageService;
        $this->notificationService = $notificationService;
        $this->jobUtility = $jobUtility;
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


    public function store($data)
    {
        return  $this->store($data);
    }


    public function findJobByID($jobId)
    {
        return $this->jobRepository->find($jobId);
    }

    public function updateJob($id, $data, $cuser)
    {
        $job = $this->jobRepository->find($id);

        $current_translator = $job->translatorJobRel->where('cancel_at', null)->first() ??
            $job->translatorJobRel->where('completed_at', '!=', null)->first();

        $log_data = [];
        $langChanged = false;

        // Handle translator change
        $changeTranslator = $this->changeTranslator($current_translator, $data, $job);
        if ($changeTranslator['translatorChanged']) {
            $log_data[] = $changeTranslator['log_data'];
        }

        // Handle due date change
        $changeDue = $this->changeDue($job->due, $data['due']);
        if ($changeDue['dateChanged']) {
            $old_time = $job->due;
            $job->due = $data['due'];
            $log_data[] = $changeDue['log_data'];
        }

        // Handle language change
        if ($job->from_language_id != $data['from_language_id']) {
            $log_data[] = [
                'old_lang' => TeHelper::fetchLanguageFromJobId($job->from_language_id),
                'new_lang' => TeHelper::fetchLanguageFromJobId($data['from_language_id'])
            ];
            $old_lang = $job->from_language_id;
            $job->from_language_id = $data['from_language_id'];
            $langChanged = true;
        }

        // Handle status change
        $changeStatus = $this->changeStatus($job, $data, $changeTranslator['translatorChanged']);
        if ($changeStatus['statusChanged']) {
            $log_data[] = $changeStatus['log_data'];
        }

        $job->admin_comments = $data['admin_comments'];
        $job->reference = $data['reference'];

        $this->logger->addInfo('USER #' . $cuser->id . ' (' . $cuser->name . ')' . ' has updated booking #'
            . $id . ' with data: ', $log_data);

        // Save job and handle notifications
        if ($job->due <= Carbon::now()) {
            $this->jobRepository->update();
            return ['status' => 'success', 'message' => 'Updated'];
        } else {
            $this->jobRepository->update($job, $data);
            $this->handleNotifications($job, $changeDue, $old_time, $changeTranslator, $langChanged, $old_lang, $current_translator);
            return ['status' => 'success', 'message' => 'Job updated with notifications'];
        }
    }


    public function updateJobDetails($job, $data, $user)
    {
        $job->user_email = $data['user_email'] ?? null;
        $job->reference = $data['reference'] ?? '';

        if (isset($data['address'])) {
            $job->address = !empty($data['address']) ? $data['address'] : $user->userMeta->address;
            $job->instructions = !empty($data['instructions']) ? $data['instructions'] : $user->userMeta->instructions;
            $job->town = !empty($data['town']) ? $data['town'] : $user->userMeta->city;
        }

        $job->save();
    }


    public function sendJobCreatedEmail($job, $user)
    {
        $email = $job->user_email ?: $user->email;
        $name = $user->name;
        $subject = 'Vi har mottagit er tolkbokning. Bokningsnr: #' . $job->id;

        $sendData = [
            'user' => $user,
            'job' => $job
        ];

        // send email
        // $this->notificationService->send($email, $name, $subject, 'emails.job-created', $sendData);
    }

    public function applySuperAdminFilters($query, $requestData)
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
        $translatorType = $this->jobUtility->getTranslatorType($cuser->userMeta);
        $languages = $this->userLanguageService->getUserLanguagesByUserId($cuser);
        $userLanguages = collect($languages)->pluck('lang_id')->all();

        $jobIds = $this->jobRepository->fetchJobs($cuser->id, $translatorType, $userLanguages, $cuser->userMeta);

        return $this->filterPotentialJobs($jobIds, $cuser->id);
    }


    private function filterPotentialJobs($jobIds, $currentUserId)
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

}

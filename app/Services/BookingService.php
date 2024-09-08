<?php

namespace DTApi\Services;

use DTApi\Repository\BookingRepository;
use DTApi\Services\NotificationService;

class BookingService
{

    public $bookingRepository;
    public $userService;
    public $jobService;
    public $distanceService;
    public $notificationService;
    public function __construct(
        BookingRepository $bookingRepository,
        UserService $userService,
        JobService $jobService,
        DistanceService $distanceService,
        NotificationService $notificationService
    ) {
        $this->bookingRepository = $bookingRepository;
        $this->userService = $userService;
        $this->jobService = $jobService;
        $this->distanceService = $distanceService;
        $this->notificationService = $notificationService;
    }

    public function index($request)
    {
        $user_id = $request->get('user_id');
        $user = $request->__authenticatedUser;

        if ($user_id) {
            $response = $this->jobService->getUsersJobs($user_id);
        }

        if ($this->userService->isAdminOrSuperAdmin($user->user_type)) {
            $response = $this->jobService->getAllJobs($request);
        }

        return $response;
    }


    public function show($id)
    {
        return $this->bookingRepository->with('translatorJobRel.user')->find($id);
    }


    public function store($request)
    {
        $data = $request->all();
        return $this->bookingRepository->store($request->__authenticatedUser, $data);
    }


    public function update($id, $request)
    {
        $data = $request->all();
        $cuser = $request->__authenticatedUser;
        return $this->bookingRepository->updateJob($id, array_except($data, ['_token', 'submit']), $cuser);
    }


    public function immediateJobEmail($request)
    {
        $data = $request->all();
        $response =  $this->bookingRepository->storeJobEmail($data);

        return $response;
    }


    public function getHistory($request)
    {
        $userId = $request->get('user_id');
        if (!$userId) {
            return null;
        }

        $page = $request->get('page', 1);
        $cuser = $this->userService->findUserByID($userId);

        if ($cuser) {
            if ($cuser->is('customer')) {
                return $this->handleCustomer($cuser);
            } elseif ($cuser->is('translator')) {
                return $this->handleTranslator($cuser, $page);
            }
        }

        return null;
    }

    private function handleCustomer($cuser)
    {
        $jobs = $this->userService->getUserPaginatedJobs($cuser);

        return [
            'emergencyJobs' => [],
            'noramlJobs' => [],
            'jobs' => $jobs,
            'cuser' => $cuser,
            'usertype' => 'customer',
            'numpages' => 0,
            'pagenum' => 1
        ];
    }

    private function handleTranslator($cuser, $pagenum)
    {
        $jobsIds = $this->jobService->getTranslatorJobsHistoric($cuser->id, $pagenum);
        $totalJobs = $jobsIds->total();
        $numPages = ceil($totalJobs / 15);

        return [
            'emergencyJobs' => [],
            'noramlJobs' => $jobsIds,
            'jobs' => $jobsIds,
            'cuser' => $cuser,
            'usertype' => 'translator',
            'numpages' => $numPages,
            'pagenum' => $pagenum
        ];
    }



    public function acceptJob($request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->jobService->acceptJob($data, $user);

        return $response;
    }



    public function acceptJobWithId($request)
    {
        $jobId = $request->get('job_id');
        $cuser = $request->__authenticatedUser;

        $job = $this->jobService->findJobByID($jobId);

        if (!Job::isTranslatorAlreadyBooked($jobId, $cuser->id, $job->due)) {
            if ($job->status === 'pending' && $this->jobService->assignTranslatorToJob($cuser->id, $jobId)) {
                $this->jobService->updateJobStatus($job, 'assigned');
                $this->notificationService->sendJobAcceptedNotification($job, $cuser);

                $response['status'] = 'success';
                $response['list']['job'] = $job;
                $response['message'] = $this->notificationService->generateSuccessMessage($job);
            } else {
                $response['status'] = 'fail';
                $response['message'] = $this->notificationService->generateFailureMessage($job);
            }
        } else {
            $response['status'] = 'fail';
            $response['message'] = "Du har redan en bokning den tiden {$job->due}. Du har inte fått denna tolkning.";
        }
    }








    public function cancelJob($request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;
        $job = $this->jobService->findJobById($data['job_id']);
        $translator = $this->jobService->getAssignedTranslator($job);

        if ($user->is('customer')) {
            return $this->cancelByCustomer($job, $translator);
        } else {
            return $this->cancelByTranslator($job, $translator);
        }
    }


    private function cancelByTranslator($job, $translator)
    {
        $response = [];
        $hoursDifference = $job->due->diffInHours(Carbon::now());

        if ($hoursDifference > 24) {
            $this->jobService->markJobPendingAndReassign($job, $translator);
            $this->notifyCustomerOfCancellation($job);
            $data = $this->jobService->jobToData($job);


            // Notify other translators
            $this->notificationService->sendNotificationTranslator($job,$data, $translator->id);

            $response['status'] = 'success';
        } else {
            $response['status'] = 'fail';
            $response['message'] = 'Du kan inte avboka en bokning som sker inom 24 timmar genom DigitalTolk. Vänligen ring på +46 73 75 86 865 och gör din avbokning over telefon. Tack!';
        }

        return $response;
    }


    private function notifyCustomerOfCancellation($job)
    {
        $customer = $this->jobService->getJobCustomer($job);
        if ($customer) {
            $language = TeHelper::fetchLanguageFromJobId($job->from_language_id);
            $msg_text = [
                "en" => "Er {$language} tolk, {$job->duration}min {$job->due}, har avbokat tolkningen. Vi letar nu efter en ny tolk som kan ersätta denne. Tack."
            ];
            if ($this->notificationService->isNeedToSendPush($customer->id)) {
                $this->notificationService->sendPushNotificationToSpecificUsers([$customer], $job->id, 'job_cancelled', $msg_text, $this->notificationService->isNeedToDelayPush($customer->id));
            }
        }
    }


    private function cancelByCustomer($job, $translator)
    {
        $response = [];
        $withdrawTime = Carbon::now();
        $hoursDifference = $withdrawTime->diffInHours($job->due);

        $data = [
            "withdraw_at" => $withdrawTime,
            "status" => $hoursDifference >= 24 ? 'withdrawbefore24' : 'withdrawafter24'
        ];
        $this->jobService->updateJobStatus($job, $data);

        $this->dispatchJobCancellationEvent($job);
        $response['status'] = 'success';
        $response['jobstatus'] = 'success';

        if ($translator) {
            $this->notifyTranslatorOfCancellation($job, $translator);
        }

        return $response;
    }

    private function notifyTranslatorOfCancellation($job, $translator)
    {
        $language = TeHelper::fetchLanguageFromJobId($job->from_language_id);
        $msg_text = [
            "en" => "Kunden har avbokat bokningen för {$language} tolk, {$job->duration}min, {$job->due}. Var god och kolla dina tidigare bokningar för detaljer."
        ];
        if ($this->notificationService->isNeedToSendPush($translator->id)) {
            $this->notificationService->sendPushNotificationToSpecificUsers([$translator], $job->id, 'job_cancelled', $msg_text, $this->notificationService->isNeedToDelayPush($translator->id));
        }
    }

    private function dispatchJobCancellationEvent($job)
    {
        Event::fire(new JobWasCanceled($job));
    }





    public function endJob($request)
    {
        $data = $request->all();
        $response = $this->bookingRepository->endJob($data);

        return $response;
    }


    public function customerNotCall($request)
    {
        $data = $request->all();

        $response = $this->bookingRepository->customerNotCall($data);

        return $response;
    }


    public function getPotentialJobs($request)
    {
        $user = $request->__authenticatedUser;
        $response = $this->jobService->getPotentialJobs($user);
        return $response;
    }

    public function distanceFeed($request)
    {
        $data = $request->only(['distance', 'time', 'jobid', 'session_time', 'flagged', 'manually_handled', 'by_admin', 'admincomment']);

        $distance = $data['distance'] ?? '';
        $time = $data['time'] ?? '';
        $jobid = $data['jobid'] ?? null;
        $session = $data['session_time'] ?? '';
        $admincomment = $data['admincomment'] ?? '';

        $flagged = $data['flagged'] === 'true' ? 'yes' : 'no';
        $manually_handled = $data['manually_handled'] === 'true' ? 'yes' : 'no';
        $by_admin = $data['by_admin'] === 'true' ? 'yes' : 'no';

        if ($flagged === 'yes' && empty($admincomment)) {
            return "Please, add comment";
        }

        if ($time || $distance) {
            $bookingData = ['distance' => $distance, 'time' => $time];
            $this->distanceService->updateByJobId($jobid, $bookingData);
        }

        if ($admincomment || $session || $flagged || $manually_handled || $by_admin) {

            $jobData = [
                'admin_comments' => $admincomment,
                'flagged' => $flagged,
                'session_time' => $session,
                'manually_handled' => $manually_handled,
                'by_admin' => $by_admin
            ];

            $this->jobService->update($jobid, $jobData);
        }

        return 'Record updated!';
    }


    public function reopen($request)
    {
        $data = $request->all();
        $response = $this->bookingRepository->reopen($data);

        return $response;
    }

    public function resendNotifications($request)
    {
        $data = $request->all();
        $job = $this->bookingRepository->find($data['jobid']);
        $job_data = $this->bookingRepository->jobToData($job);
        $this->bookingRepository->sendNotificationTranslator($job, $job_data, '*');

        return ['success' => 'Push sent'];
    }


    public function resendSMSNotifications($request)
    {
        $data = $request->all();
        $job = $this->bookingRepository->find($data['jobid']);
        $job_data = $this->bookingRepository->jobToData($job);

        try {
            $this->bookingRepository->sendSMSNotificationToTranslator($job);
            return ['success' => 'SMS sent'];
        } catch (\Exception $e) {
            return ['success' => $e->getMessage()];
        }
    }
}

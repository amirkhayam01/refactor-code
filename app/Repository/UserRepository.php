<?php

namespace DTApi\Repository;

use DTApi\Repository\BaseRepository;
use DTApi\Models\User;

class UserRepository extends BaseRepository
{

    public function __construct(User $user)
    {
        parent::__construct($user);
    }

    public function findUserByEmail($email)
    {
        return $this->query()->where('email', $email)->first();
    }

    public function findUserByCustomerType($consumerType)
    {
        return $this->query()->whereHas('userMeta', function ($q) use ($consumerType) {
            $q->where('consumer_type', $consumerType);
        });
    }

    public function getUsersByEmail($emails)
    {
        return $this->query()->whereIn('email', $emails)->get();
    }

    public function getUserIdsByEmails($emails)
    {
        return $this->query()->whereIn('email', $emails)->pluck('id');
    }


    public function getUserJobs($user)
    {
        return $user->jobs()->with('user.userMeta', 'user.average', 'translatorJobRel.user.average', 'language', 'feedback')
            ->whereIn('status', ['pending', 'assigned', 'started'])
            ->orderBy('due', 'asc')->get();
    }


    public function getUserByJob($job)
    {
        return $job->user()->first();
    }


    public function getUserPaginatedJobs($cuser)
    {
        return $cuser->jobs()
            ->with('user.userMeta', 'user.average', 'translatorJobRel.user.average', 'language', 'feedback', 'distance')
            ->whereIn('status', ['completed', 'withdrawbefore24', 'withdrawafter24', 'timedout'])
            ->orderBy('due', 'desc')
            ->paginate(15);
    }


    /**
     * @param $user_id
     * @return array
     */
    public function getUsersJobsHistory($user_id, $request)
    {
        $page = $request->get('page');
        if (isset($page)) {
            $pagenum = $page;
        } else {
            $pagenum = "1";
        }
        $cuser = $this->find($user_id);
        $usertype = '';
        $emergencyJobs = array();
        $noramlJobs = array();
        if ($cuser && $cuser->is('customer')) {
            $jobs = $cuser->jobs()->with('user.userMeta', 'user.average', 'translatorJobRel.user.average', 'language', 'feedback', 'distance')->whereIn('status', ['completed', 'withdrawbefore24', 'withdrawafter24', 'timedout'])->orderBy('due', 'desc')->paginate(15);
            $usertype = 'customer';
            return ['emergencyJobs' => $emergencyJobs, 'noramlJobs' => [], 'jobs' => $jobs, 'cuser' => $cuser, 'usertype' => $usertype, 'numpages' => 0, 'pagenum' => 0];
        } elseif ($cuser && $cuser->is('translator')) {
            $jobs_ids = Job::getTranslatorJobsHistoric($cuser->id, 'historic', $pagenum);
            $totaljobs = $jobs_ids->total();
            $numpages = ceil($totaljobs / 15);

            $usertype = 'translator';

            $jobs = $jobs_ids;
            $noramlJobs = $jobs_ids;
            return ['emergencyJobs' => $emergencyJobs, 'noramlJobs' => $noramlJobs, 'jobs' => $jobs, 'cuser' => $cuser, 'usertype' => $usertype, 'numpages' => $numpages, 'pagenum' => $pagenum];
        }
    }
}

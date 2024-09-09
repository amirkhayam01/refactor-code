<?php

namespace DTApi\Repository;

use DTApi\Repository\BaseRepository;
use DTApi\Models\Job;

class JobRepository extends BaseRepository
{

    public function __construct(Job $model)
    {
        parent::__construct($model);
    }

    public function findJobsByIDs($query, $ids)
    {
        return $query->whereIn('id', $ids);
    }


    public function findJobsByUserIDs($ids)
    {
        return $this->query()->whereIn('user_id', $ids);
    }


    public function filterJobsByFeedback($query)
    {
        return $query->where('ignore_feedback', '0')
            ->whereHas('feedback', function ($query) {
                $query->where('rating', '<=', 3);
            });
    }

    public function filterJobsByLang($query, $langIds)
    {
        return $query->whereIn('from_language_id', $langIds);
    }

    public function filterJobsByStatus($query, $status)
    {
        return $query->whereIn('status', $status);
    }


    public function filterJobsByDates($query, $from, $to, $column = 'created_at')
    {
        if ($from) {
            $query->where($column, '>=', $from);
        }
        if ($to) {
            $query->where($column, '<=', $to . ' 23:59:00');
        }

        return $query;
    }

    public function filterJobsByExpiredDate($query, $date)
    {
        return $query->where('expired_at', '>=', $date);
    }

    public function filterJobsByExpiryDate($query, $date)
    {
        return $query->where('will_expire_at', '>=', $date);
    }

    public function filterByJobType($query, $jobTypes)
    {
        return $query->whereIn('job_type', $jobTypes);
    }


    public function filterByPhysicalType($query, $physicalType)
    {
        return $query->where('customer_physical_type', $physicalType)->where('ignore_physical', 0);
    }

    public function filterByPhone($query, $phone)
    {
        if (isset($phone)) {
            $query->where('customer_phone_type', $phone);
            if (isset($filters['physical'])) {
                $query->where('ignore_physical_phone', 0);
            }

            return $query;
        }
    }

    public function filterByFlagged($query, $flagged)
    {
        return  $query->where('flagged', $flagged)->where('ignore_flagged', 0);
    }

    public function filterByEmptyDistance($query)
    {
        return $query->whereDoesntHave('distance');
    }

    public function filterBySalary($query)
    {
        return $query->whereDoesntHave('user.salaries');
    }


    public function filterByConsumerType($query, $customerType)
    {
        return $query->whereHas('user.userMeta', function ($q) use ($customerType) {
            $q->where('consumer_type', $customerType);
        });
    }

    public function filterByBookingType($query, $bookingType)
    {
        if ($bookingType == 'physical') {
            return $query->where('customer_physical_type', 'yes');
        }
        if ($bookingType == 'phone') {
            return  $query->where('customer_phone_type', 'yes');
        }
    }


    public function filterByTime($query, $requestData)
    {
        if ($requestData['filter_timetype'] === "created") {
            if (!empty($requestData['from'])) {
                $query->where('created_at', '>=', $requestData['from']);
            }
            if (!empty($requestData['to'])) {
                $to = $requestData['to'] . " 23:59:00";
                $query->where('created_at', '<=', $to);
            }
            $query->orderBy('created_at', 'desc');
        }

        if ($requestData['filter_timetype'] === "due") {
            if (!empty($requestData['from'])) {
                $query->where('due', '>=', $requestData['from']);
            }
            if (!empty($requestData['to'])) {
                $to = $requestData['to'] . " 23:59:00";
                $query->where('due', '<=', $to);
            }
            $query->orderBy('due', 'desc');
        }

        return $query;
    }

    public function getJobsWithRelations($limit = 15)
    {
        return $this->query()->with(['user', 'language', 'feedback.user', 'translatorJobRel.user', 'distance'])
            ->paginate($limit);
    }

    public function getTranslatorJobs($userId)
    {
        return $this->query()->getTranslatorJobs($userId, 'new')->pluck('jobs')->all();
    }


    public function getTranslatorJobsHistoric($userId, $pagenum)
    {
        return $this->query()->getTranslatorJobsHistoric($userId, 'historic', $pagenum);
    }


    public function isTranslatorAlreadyBooked($job_id, $userId, $job_due)
    {
        return Job::isTranslatorAlreadyBooked($job_id, $userId, $job_due);
    }


    public function getJobCustomer($job)
    {
        return $job->user()->first();
    }

    public function fetchJobs($userId, $jobType, $userLanguages, $userMeta)
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
}

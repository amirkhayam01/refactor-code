<?php

namespace DTApi\Services;

use DTApi\Repository\UserRepository;

class UserService
{

    public $userRepository;
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function isSuperAdmin($user)
    {
        return $user && $user->user_type == env('SUPERADMIN_ROLE_ID');
    }


    public function isAdminOrSuperAdmin($userType)
    {
        $adminRoleId = config('roles.admin');
        $superAdminRoleId = config('roles.superadmin');

        return in_array($userType, [$adminRoleId, $superAdminRoleId]);
    }


    public function isCustomer($user)
    {
        return $user && $user->is('customer');
    }


    public function isTranslator($user)
    {
        return $user && $user->is('translator');
    }


    public function findUserByID($userId)
    {
        return $this->userRepository->find($userId);
    }

    public function getUsersByEmail($emails)
    {
        return $this->userRepository->getUsersByEmail($emails);
    }

    public function findUserByEmail($email)
    {
        return $this->userRepository->findUserByEmail($email);
    }

    public function getUserIdsByEmail($emails)
    {
        return $this->userRepository->getUserIdsByEmails($emails);
    }

    public function getUserPaginatedJobs($user)
    {
        return $this->userRepository->getUserPaginatedJobs($user);
    }

    public function getUserByJob($job) {
        return $this->userRepository->getUserByJob($job);

    }
}

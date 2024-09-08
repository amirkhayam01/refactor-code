<?php

namespace DTApi\Http\Controllers;

use Illuminate\Http\Request;
use DTApi\Services\UserService;
use DTApi\Services\BookingService;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    protected $userService;
    protected $bookingService;
    /**
     * BookingController constructor.
     * @param BookingService $bookingService
     */
    public function __construct(BookingService $bookingService, UserService $userService)
    {
        $this->userService = $userService;
        $this->bookingService = $bookingService;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $response = $this->bookingService->index($request);
        return response($response);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $response = $this->bookingService->show($id);
        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $response = $this->bookingService->store($request);
        return response($response);
    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        $response = $this->bookingService->update($id, $request);
        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        $response = $this->bookingService->immediateJobEmail($request);
        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        $response = $this->bookingService->getHistory($request);
        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {

        $response = $this->bookingService->acceptJob($request);
        return response($response);
    }

    public function acceptJobWithId(Request $request)
    {

        $response = $this->bookingService->acceptJobWithId($request);
        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {

        $response = $this->bookingService->cancelJob($request);
        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {

        $response = $this->bookingService->endJob($request);
        return response($response);
    }

    public function customerNotCall(Request $request)
    {

        $response = $this->bookingService->customerNotCall($request);
        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {

        $response = $this->bookingService->getPotentialJobs($request);
        return response($response);
    }

    public function distanceFeed(Request $request)
    {

        $response = $this->bookingService->distanceFeed($request);
        return response($response);
    }

    public function reopen(Request $request)
    {

        $response = $this->bookingService->reopen($request);
        return response($response);
    }

    public function resendNotifications(Request $request)
    {

        $response = $this->bookingService->resendNotifications($request);
        return response($response);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {

        $response = $this->bookingService->resendSMSNotifications($request);
        return response($response);
    }
}

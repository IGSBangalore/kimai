<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\API;

use App\Entity\Timesheet;
use App\Entity\User;
use App\Event\RecentActivityEvent;
use App\Event\TimesheetDuplicatePostEvent;
use App\Event\TimesheetDuplicatePreEvent;
use App\Event\TimesheetMetaDefinitionEvent;
use App\Form\API\TimesheetApiEditForm;
use App\Repository\Query\TimesheetQuery;
use App\Repository\TagRepository;
use App\Repository\TimesheetRepository;
use App\Timesheet\TimesheetService;
use App\Timesheet\TrackingMode\TrackingModeInterface;
use App\Utils\SearchTerm;
use App\Validator\ValidationFailedException;
use Doctrine\Common\Collections\ArrayCollection;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use Nelmio\ApiDocBundle\Annotation\Security as ApiSecurity;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @Route(path="/timesheets")
 * @OA\Tag(name="Timesheet")
 *
 * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED')")
 */
final class TimesheetController extends BaseApiController
{
    public const GROUPS_ENTITY = ['Default', 'Entity', 'Timesheet', 'Timesheet_Entity', 'Not_Expanded'];
    public const GROUPS_ENTITY_FULL = ['Default', 'Entity', 'Timesheet', 'Timesheet_Entity', 'Expanded'];
    public const GROUPS_FORM = ['Default', 'Entity', 'Timesheet', 'Not_Expanded'];
    public const GROUPS_COLLECTION = ['Default', 'Collection', 'Timesheet', 'Not_Expanded'];
    public const GROUPS_COLLECTION_FULL = ['Default', 'Collection', 'Timesheet', 'Expanded'];

    public function __construct(
        private ViewHandlerInterface $viewHandler,
        private TimesheetRepository $repository,
        private TagRepository $tagRepository,
        private EventDispatcherInterface $dispatcher,
        private TimesheetService $service
    ) {
    }

    protected function getTrackingMode(): TrackingModeInterface
    {
        return $this->service->getActiveTrackingMode();
    }

    /**
     * Returns a collection of timesheet records
     *
     * @OA\Response(
     *      response=200,
     *      description="Returns a collection of timesheets records. Be aware that the datetime fields are given in the users local time including the timezone offset via ISO 8601.",
     *      @OA\JsonContent(
     *          type="array",
     *          @OA\Items(ref="#/components/schemas/TimesheetCollection")
     *      )
     * )
     *
     * @Rest\QueryParam(name="user", requirements="\d+|all", strict=true, nullable=true, description="User ID to filter timesheets. Needs permission 'view_other_timesheet', pass 'all' to fetch data for all user (default: current user)")
     * @Rest\QueryParam(name="customer", requirements="\d+", strict=true, nullable=true, description="Customer ID to filter timesheets")
     * @Rest\QueryParam(name="customers", requirements="[\d|,]+", strict=true, nullable=true, description="Comma separated list of customer IDs to filter timesheets")
     * @Rest\QueryParam(name="project", requirements="\d+", strict=true, nullable=true, description="Project ID to filter timesheets")
     * @Rest\QueryParam(name="projects", requirements="[\d|,]+", strict=true, nullable=true, description="Comma separated list of project IDs to filter timesheets")
     * @Rest\QueryParam(name="activity", requirements="\d+", strict=true, nullable=true, description="Activity ID to filter timesheets")
     * @Rest\QueryParam(name="activities", requirements="[\d|,]+", strict=true, nullable=true, description="Comma separated list of activity IDs to filter timesheets")
     * @Rest\QueryParam(name="page", requirements="\d+", strict=true, nullable=true, description="The page to display, renders a 404 if not found (default: 1)")
     * @Rest\QueryParam(name="size", requirements="\d+", strict=true, nullable=true, description="The amount of entries for each page (default: 50)")
     * @Rest\QueryParam(name="tags", strict=true, nullable=true, description="Comma separated list of tag names")
     * @Rest\QueryParam(name="orderBy", requirements="id|begin|end|rate", strict=true, nullable=true, description="The field by which results will be ordered. Allowed values: id, begin, end, rate (default: begin)")
     * @Rest\QueryParam(name="order", requirements="ASC|DESC", strict=true, nullable=true, description="The result order. Allowed values: ASC, DESC (default: DESC)")
     * @Rest\QueryParam(name="begin", requirements=@Constraints\DateTime(format="Y-m-d\TH:i:s"), strict=true, nullable=true, description="Only records after this date will be included (format: HTML5)")
     * @Rest\QueryParam(name="end", requirements=@Constraints\DateTime(format="Y-m-d\TH:i:s"), strict=true, nullable=true, description="Only records before this date will be included (format: HTML5)")
     * @Rest\QueryParam(name="exported", requirements="0|1", strict=true, nullable=true, description="Use this flag if you want to filter for export state. Allowed values: 0=not exported, 1=exported (default: all)")
     * @Rest\QueryParam(name="active", requirements="0|1", strict=true, nullable=true, description="Filter for running/active records. Allowed values: 0=stopped, 1=active (default: all)")
     * @Rest\QueryParam(name="billable", requirements="0|1", strict=true, nullable=true, description="Filter for non-/billable records. Allowed values: 0=non-billable, 1=billable (default: all)")
     * @Rest\QueryParam(name="full", requirements="true", strict=true, nullable=true, description="Allows to fetch fully serialized objects including subresources. Allowed values: true (default: false)")
     * @Rest\QueryParam(name="term", description="Free search term")
     * @Rest\QueryParam(name="modified_after", requirements=@Constraints\DateTime(format="Y-m-d\TH:i:s"), strict=true, nullable=true, description="Only records changed after this date will be included (format: HTML5). Available since Kimai 1.10 and works only for records that were created/updated since then.")
     *
     * @Rest\Get(path="", name="get_timesheets")
     *
     * @Security("is_granted('view_own_timesheet') or is_granted('view_other_timesheet')")
     *
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     */
    public function cgetAction(ParamFetcherInterface $paramFetcher): Response
    {
        $query = new TimesheetQuery(false);
        $query->setUser($this->getUser());

        if ($this->isGranted('view_other_timesheet') && null !== ($user = $paramFetcher->get('user'))) {
            if ('all' === $user) {
                $user = null;
            }
            $query->setUser($user);
        }

        if (!empty($customers = $paramFetcher->get('customers'))) {
            if (!\is_array($customers)) {
                $customers = explode(',', $customers);
            }
            $query->setCustomers($customers);
        }

        if (!empty($customer = $paramFetcher->get('customer'))) {
            $query->addCustomer($customer);
        }

        if (!empty($projects = $paramFetcher->get('projects'))) {
            if (!\is_array($projects)) {
                $projects = explode(',', $projects);
            }
            $query->setProjects($projects);
        }

        if (!empty($project = $paramFetcher->get('project'))) {
            $query->addProject($project);
        }

        if (!empty($activities = $paramFetcher->get('activities'))) {
            if (!\is_array($activities)) {
                $activities = explode(',', $activities);
            }
            $query->setActivities($activities);
        }

        if (!empty($activity = $paramFetcher->get('activity'))) {
            $query->addActivity($activity);
        }

        if (null !== ($page = $paramFetcher->get('page'))) {
            $query->setPage($page);
        }

        if (null !== ($size = $paramFetcher->get('size'))) {
            $query->setPageSize($size);
        }

        if (null !== ($tags = $paramFetcher->get('tags'))) {
            $ids = $this->tagRepository->findIdsByTagNameList($tags);
            if (\count($ids) > 0) {
                $query->setTags(new ArrayCollection($ids));
            }
        }

        if (null !== ($order = $paramFetcher->get('order'))) {
            $query->setOrder($order);
        }

        if (null !== ($orderBy = $paramFetcher->get('orderBy'))) {
            $query->setOrderBy($orderBy);
        }

        $factory = $this->getDateTimeFactory();

        if (null !== ($begin = $paramFetcher->get('begin'))) {
            $query->setBegin($factory->createDateTime($begin));
        }

        if (null !== ($end = $paramFetcher->get('end'))) {
            $query->setEnd($factory->createDateTime($end));
        }

        if (null !== ($active = $paramFetcher->get('active'))) {
            $active = (int) $active;
            if ($active === 1) {
                $query->setState(TimesheetQuery::STATE_RUNNING);
            } elseif ($active === 0) {
                $query->setState(TimesheetQuery::STATE_STOPPED);
            }
        }

        if (null !== ($billable = $paramFetcher->get('billable'))) {
            $billable = (int) $billable;
            if ($billable === 1) {
                $query->setBillable(true);
            } elseif ($billable === 0) {
                $query->setBillable(false);
            }
        }

        if (null !== ($exported = $paramFetcher->get('exported'))) {
            $exported = (int) $exported;
            if ($exported === 1) {
                $query->setExported(TimesheetQuery::STATE_EXPORTED);
            } elseif ($exported === 0) {
                $query->setExported(TimesheetQuery::STATE_NOT_EXPORTED);
            }
        }

        if (!empty($term = $paramFetcher->get('term'))) {
            $query->setSearchTerm(new SearchTerm($term));
        }

        if (!empty($modifiedAfter = $paramFetcher->get('modified_after'))) {
            $query->setModifiedAfter($factory->createDateTime($modifiedAfter));
        }

        $data = $this->repository->getPagerfantaForQuery($query);
        $data = (array) $data->getCurrentPageResults();

        $view = new View($data, 200);
        if ('true' === $paramFetcher->get('full')) {
            $view->getContext()->setGroups(self::GROUPS_COLLECTION_FULL);
        } else {
            $view->getContext()->setGroups(self::GROUPS_COLLECTION);
        }

        return $this->viewHandler->handle($view);
    }

    /**
     * Returns one timesheet record
     *
     * @OA\Response(
     *      response=200,
     *      description="Returns one timesheet record. Be aware that the datetime fields are given in the users local time including the timezone offset via ISO 8601.",
     *      @OA\JsonContent(ref="#/components/schemas/TimesheetEntity")
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="path",
     *      description="Timesheet record ID to fetch",
     *      required=true,
     * )
     *
     * @Rest\Get(path="/{id}", name="get_timesheet", requirements={"id": "\d+"})
     *
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     *
     * @Security("is_granted('view', id)")
     */
    public function getAction(Timesheet $id): Response
    {
        $view = new View($id, 200);
        $view->getContext()->setGroups(self::GROUPS_ENTITY);

        return $this->viewHandler->handle($view);
    }

    /**
     * Creates a new timesheet record
     *
     * @OA\Post(
     *      description="Creates a new timesheet record for the current user and returns it afterwards.",
     *      @OA\Response(
     *          response=200,
     *          description="Returns the new created timesheet",
     *          @OA\JsonContent(ref="#/components/schemas/TimesheetEntity"),
     *      )
     * )
     * @OA\RequestBody(
     *      required=true,
     *      @OA\JsonContent(ref="#/components/schemas/TimesheetEditForm"),
     * )
     *
     * @Rest\QueryParam(name="full", requirements="true", strict=true, nullable=true, description="Allows to fetch fully serialized objects including subresources (TimesheetExpanded). Allowed values: true (default: false)")
     *
     * @Rest\Post(path="", name="post_timesheet")
     *
     * @Security("is_granted('create_own_timesheet')")
     *
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     */
    public function postAction(Request $request, ParamFetcherInterface $paramFetcher): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $timesheet = $this->service->createNewTimesheet($user, $request);

        $mode = $this->getTrackingMode();

        $form = $this->createForm(TimesheetApiEditForm::class, $timesheet, [
            'include_rate' => $this->isGranted('edit_rate', $timesheet),
            'include_exported' => $this->isGranted('edit_export', $timesheet),
            'include_billable' => $this->isGranted('edit_billable', $timesheet),
            'include_user' => $this->isGranted('create_other_timesheet'),
            'allow_begin_datetime' => $mode->canUpdateTimesWithAPI(),
            'allow_end_datetime' => $mode->canUpdateTimesWithAPI(),
            'date_format' => self::DATE_FORMAT,
        ]);

        $form->submit($request->request->all(), false);

        if ($form->isValid()) {
            try {
                $this->service->saveNewTimesheet($timesheet);

                $view = new View($timesheet, 200);

                if ('true' === $paramFetcher->get('full')) {
                    $view->getContext()->setGroups(self::GROUPS_ENTITY_FULL);
                } else {
                    $view->getContext()->setGroups(self::GROUPS_ENTITY);
                }

                return $this->viewHandler->handle($view);
            } catch (ValidationFailedException $ex) {
                $form->addError(new FormError($ex->getMessage()));
            }
        }

        $view = new View($form);
        $view->getContext()->setGroups(self::GROUPS_FORM);

        return $this->viewHandler->handle($view);
    }

    /**
     * Update an existing timesheet record
     *
     * @OA\Patch(
     *      description="Update an existing timesheet record, you can pass all or just a subset of the attributes.",
     *      @OA\Response(
     *          response=200,
     *          description="Returns the updated timesheet",
     *          @OA\JsonContent(ref="#/components/schemas/TimesheetEntity")
     *      )
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="path",
     *      description="Timesheet record ID to update",
     *      required=true,
     * )
     * @OA\RequestBody(
     *      required=true,
     *      @OA\JsonContent(ref="#/components/schemas/TimesheetEditForm"),
     * )
     * @Rest\Patch(path="/{id}", name="patch_timesheet", requirements={"id": "\d+"})
     *
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     *
     * @Security("is_granted('edit', id)")
     */
    public function patchAction(Request $request, Timesheet $id): Response
    {
        $timesheet = $id;
        $event = new TimesheetMetaDefinitionEvent($timesheet);
        $this->dispatcher->dispatch($event);

        $mode = $this->getTrackingMode();

        $form = $this->createForm(TimesheetApiEditForm::class, $timesheet, [
            'include_rate' => $this->isGranted('edit_rate', $timesheet),
            'include_exported' => $this->isGranted('edit_export', $timesheet),
            'include_billable' => $this->isGranted('edit_billable', $timesheet),
            'include_user' => $this->isGranted('edit', $timesheet),
            'allow_begin_datetime' => $mode->canUpdateTimesWithAPI(),
            'allow_end_datetime' => $mode->canUpdateTimesWithAPI(),
            'date_format' => self::DATE_FORMAT,
        ]);

        $form->setData($timesheet);
        $form->submit($request->request->all(), false);

        if (false === $form->isValid()) {
            $view = new View($form, Response::HTTP_OK);
            $view->getContext()->setGroups(self::GROUPS_FORM);

            return $this->viewHandler->handle($view);
        }

        $this->service->updateTimesheet($timesheet);

        $view = new View($timesheet, Response::HTTP_OK);
        $view->getContext()->setGroups(self::GROUPS_ENTITY);

        return $this->viewHandler->handle($view);
    }

    /**
     * Delete an existing timesheet record
     *
     * @OA\Delete(
     *      @OA\Response(
     *          response=204,
     *          description="Delete one timesheet record"
     *      ),
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="path",
     *      description="Timesheet record ID to delete",
     *      required=true,
     * )
     * @Rest\Delete(path="/{id}", name="delete_timesheet", requirements={"id": "\d+"})
     *
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     *
     * @Security("is_granted('delete', id)")
     */
    public function deleteAction(Timesheet $id): Response
    {
        $this->service->deleteTimesheet($id);

        $view = new View(null, Response::HTTP_NO_CONTENT);

        return $this->viewHandler->handle($view);
    }

    /**
     * Returns the collection of recent user activities
     *
     * @OA\Response(
     *      response=200,
     *      description="Returns the collection of recent user activities (always the latest entry of a unique working set grouped by customer, project and activity)",
     *      @OA\JsonContent(
     *          type="array",
     *          @OA\Items(ref="#/components/schemas/TimesheetCollectionExpanded")
     *      )
     * )
     *
     * @Rest\QueryParam(name="begin", requirements=@Constraints\DateTime(format="Y-m-d\TH:i:s"), strict=true, nullable=true, description="Only records after this date will be included. Default: today - 1 year (format: HTML5)")
     * @Rest\QueryParam(name="size", requirements="\d+", strict=true, nullable=true, description="The amount of entries (default: 10)")
     *
     * @Rest\Get(path="/recent", name="recent_timesheet")
     *
     * @Security("is_granted('view_own_timesheet')")
     *
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     */
    public function recentAction(ParamFetcherInterface $paramFetcher): Response
    {
        $user = $this->getUser();
        $begin = null;
        $limit = 10;

        if (null !== ($reqLimit = $paramFetcher->get('size'))) {
            $limit = (int) $reqLimit;
        }

        if (null !== ($reqBegin = $paramFetcher->get('begin'))) {
            $begin = $this->getDateTimeFactory($user)->createDateTime($reqBegin);
        }

        $data = $this->repository->getRecentActivities($user, $begin, $limit);

        $recentActivity = new RecentActivityEvent($user, $data);
        $this->dispatcher->dispatch($recentActivity);

        $view = new View($recentActivity->getRecentActivities(), 200);
        $view->getContext()->setGroups(self::GROUPS_COLLECTION_FULL);

        return $this->viewHandler->handle($view);
    }

    /**
     * Returns the collection of active timesheet records
     *
     * @OA\Response(
     *      response=200,
     *      description="Returns the collection of active timesheet records for the current user",
     *      @OA\JsonContent(
     *          type="array",
     *          @OA\Items(ref="#/components/schemas/TimesheetCollectionExpanded")
     *      )
     * )
     *
     * @Rest\Get(path="/active", name="active_timesheet")
     *
     * @Security("is_granted('view_own_timesheet')")
     *
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     */
    public function activeAction(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = $this->repository->getActiveEntries($user);

        $view = new View($data, 200);
        $view->getContext()->setGroups(self::GROUPS_COLLECTION_FULL);

        return $this->viewHandler->handle($view);
    }

    /**
     * Stops an active timesheet record
     *
     * @OA\Response(
     *      response=200,
     *      description="Stops an active timesheet record and returns it afterwards.",
     *      @OA\JsonContent(ref="#/components/schemas/TimesheetEntity")
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="path",
     *      description="Timesheet record ID to stop",
     *      required=true,
     * )
     * @Rest\Patch(path="/{id}/stop", name="stop_timesheet", requirements={"id": "\d+"})
     *
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     *
     * @Security("is_granted('stop', id)")
     */
    public function stopAction(Timesheet $id): Response
    {
        $this->service->stopTimesheet($id);

        $view = new View($id, 200);
        $view->getContext()->setGroups(self::GROUPS_ENTITY);

        return $this->viewHandler->handle($view);
    }

    /**
     * Restarts a previously stopped timesheet record for the current user
     *
     * @OA\Response(
     *      response=200,
     *      description="Restarts a timesheet record for the same customer, project, activity combination. The current user will be the owner of the new record. Kimai tries to stop running records, which is expected to fail depending on the configured rules. Data will be copied from the original record if requested.",
     *      @OA\JsonContent(ref="#/components/schemas/TimesheetEntity")
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="path",
     *      description="Timesheet record ID to restart",
     *      required=true,
     * )
     *
     * @Rest\RequestParam(name="copy", requirements="all", strict=true, nullable=true, description="Whether data should be copied to the new entry. Allowed values: all (default: nothing is copied)")
     * @Rest\RequestParam(name="begin", requirements=@Constraints\DateTime(format="Y-m-d\TH:i:s"), strict=true, nullable=true, description="Changes the restart date to the given one (default: now)")
     *
     * @Rest\Patch(path="/{id}/restart", name="restart_timesheet", requirements={"id": "\d+"})
     *
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     *
     * @Security("is_granted('start', id)")
     */
    public function restartAction(Timesheet $id, ParamFetcherInterface $paramFetcher): Response
    {
        $timesheet = $id;
        /** @var User $user */
        $user = $this->getUser();

        $copyTimesheet = $this->service->createNewTimesheet($user);

        $factory = $this->getDateTimeFactory();

        $begin = $factory->createDateTime();
        if (null !== ($beginTmp = $paramFetcher->get('begin'))) {
            $begin = $factory->createDateTime($beginTmp);
        }

        $copyTimesheet
            ->setBegin($begin)
            ->setActivity($timesheet->getActivity())
            ->setProject($timesheet->getProject())
        ;

        if (null !== ($copy = $paramFetcher->get('copy'))) {
            $copyTimesheet->setHourlyRate($timesheet->getHourlyRate());
            $copyTimesheet->setFixedRate($timesheet->getFixedRate());
            $copyTimesheet->setDescription($timesheet->getDescription());
            $copyTimesheet->setBillable($timesheet->isBillable());

            foreach ($timesheet->getTags() as $tag) {
                $copyTimesheet->addTag($tag);
            }

            foreach ($timesheet->getMetaFields() as $metaField) {
                $metaNew = clone $metaField;
                $copyTimesheet->setMetaField($metaNew);
            }
        }

        // needs to be executed AFTER copying the values!
        // the event triggered in prepareNewTimesheet() will add meta fields first. Afterwards
        // setMetaField() will merge meta fields and if we merge in the existing ones from the copied record,
        // it will fail, because they do not have a type assigned
        $this->service->prepareNewTimesheet($copyTimesheet);

        $this->service->restartTimesheet($copyTimesheet, $timesheet);

        $view = new View($copyTimesheet, 200);
        $view->getContext()->setGroups(self::GROUPS_ENTITY);

        return $this->viewHandler->handle($view);
    }

    /**
     * Duplicates an existing timesheet record
     *
     * @OA\Response(
     *      response=200,
     *      description="Duplicates a timesheet record, resetting the export state only.",
     *      @OA\JsonContent(ref="#/components/schemas/TimesheetEntity")
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="path",
     *      description="Timesheet record ID to duplicate",
     *      required=true,
     * )
     * @Rest\Patch(path="/{id}/duplicate", name="duplicate_timesheet", requirements={"id": "\d+"})
     *
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     *
     * @Security("is_granted('duplicate', id)")
     */
    public function duplicateAction(Timesheet $id): Response
    {
        $timesheet = $id;
        $copyTimesheet = clone $timesheet;

        $this->dispatcher->dispatch(new TimesheetDuplicatePreEvent($copyTimesheet, $timesheet));
        $this->service->saveNewTimesheet($copyTimesheet);
        $this->dispatcher->dispatch(new TimesheetDuplicatePostEvent($copyTimesheet, $timesheet));

        $view = new View($copyTimesheet, 200);
        $view->getContext()->setGroups(self::GROUPS_ENTITY);

        return $this->viewHandler->handle($view);
    }

    /**
     * Switch the export state of a timesheet record to (un-)lock it
     *
     * @OA\Response(
     *      response=200,
     *      description="Switches the exported state on the record and therefor locks / unlocks it for further updates. Needs edit_export_*_timesheet permission.",
     *      @OA\JsonContent(ref="#/components/schemas/TimesheetEntity")
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="path",
     *      description="Timesheet record ID to switch export state",
     *      required=true,
     * )
     * @Rest\Patch(path="/{id}/export", name="export_timesheet", requirements={"id": "\d+"})
     *
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     *
     * @Security("is_granted('edit_export', id)")
     */
    public function exportAction(Timesheet $id): Response
    {
        $timesheet = $id;

        if ($timesheet->isExported() && !$this->isGranted('edit_exported_timesheet')) {
            throw new AccessDeniedHttpException('User cannot edit an exported timesheet');
        }

        $timesheet->setExported(!$timesheet->isExported());

        $this->service->updateTimesheet($timesheet);

        $view = new View($timesheet, 200);
        $view->getContext()->setGroups(self::GROUPS_ENTITY);

        return $this->viewHandler->handle($view);
    }

    /**
     * Sets the value of a meta-field for an existing timesheet.
     *
     * @OA\Response(
     *      response=200,
     *      description="Sets the value of an existing/configured meta-field. You cannot create unknown meta-fields, if the given name is not a configured meta-field, this will return an exception.",
     *      @OA\JsonContent(ref="#/components/schemas/TimesheetEntity")
     * )
     * @OA\Parameter(
     *      name="id",
     *      in="path",
     *      description="Timesheet record ID to set the meta-field value for",
     *      required=true,
     * )
     * @Rest\RequestParam(name="name", strict=true, nullable=false, description="The meta-field name")
     * @Rest\RequestParam(name="value", strict=true, nullable=false, description="The meta-field value")
     *
     * @Rest\Patch(path="/{id}/meta", requirements={"id": "\d+"})
     *
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     *
     * @Security("is_granted('edit', id)")
     */
    public function metaAction(Timesheet $id, ParamFetcherInterface $paramFetcher): Response
    {
        $timesheet = $id;
        $event = new TimesheetMetaDefinitionEvent($timesheet);
        $this->dispatcher->dispatch($event);

        $name = $paramFetcher->get('name');
        $value = $paramFetcher->get('value');

        if (null === ($meta = $timesheet->getMetaField($name))) {
            throw new \InvalidArgumentException('Unknown meta-field requested');
        }

        $meta->setValue($value);

        $this->service->updateTimesheet($timesheet);

        $view = new View($timesheet, 200);
        $view->getContext()->setGroups(self::GROUPS_ENTITY);

        return $this->viewHandler->handle($view);
    }
}

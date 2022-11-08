<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Configuration\SystemConfiguration;
use App\Entity\Timesheet;
use App\Form\QuickEntryForm;
use App\Model\QuickEntryModel;
use App\Model\QuickEntryWeek;
use App\Repository\Query\TimesheetQuery;
use App\Repository\TimesheetRepository;
use App\Timesheet\TimesheetService;
use App\Utils\PageSetup;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller used to enter times in weekly form.
 */
#[Route(path: '/quick_entry')]
#[Security("is_granted('quick-entry')")]
class QuickEntryController extends AbstractController
{
    public function __construct(private SystemConfiguration $configuration, private TimesheetService $timesheetService, private TimesheetRepository $repository)
    {
    }

    #[Route(path: '/{begin}', name: 'quick_entry', methods: ['GET', 'POST'])]
    public function quickEntry(Request $request, ?string $begin = null)
    {
        $factory = $this->getDateTimeFactory();
        if ($begin === null) {
            $begin = $factory->createDateTime();
        } else {
            $begin = $factory->createDateTime($begin);
        }

        $startWeek = $factory->getStartOfWeek($begin);
        $endWeek = $factory->getEndOfWeek($begin);
        $user = $this->getUser();

        $tmpDay = clone $startWeek;
        $week = [];
        while ($tmpDay < $endWeek) {
            $nextDay = clone $tmpDay;
            $week[$nextDay->format('Y-m-d')] = ['day' => $nextDay];
            $tmpDay = $tmpDay->modify('+1 day');
        }

        $query = new TimesheetQuery();
        $query->setBegin($startWeek);
        $query->setEnd($endWeek);
        $query->setName('quickEntryForm');
        $query->setUser($user);

        $result = $this->repository->getTimesheetResult($query);

        $rows = [];
        /** @var Timesheet $timesheet */
        foreach ($result->getResults(true) as $timesheet) {
            $i = 0;
            $id = $timesheet->getProject()->getId() . '_' . $timesheet->getActivity()->getId();
            $day = $timesheet->getBegin()->format('Y-m-d');

            while (\array_key_exists($id, $rows) && \array_key_exists('entry', $rows[$id]['days'][$day])) {
                $i++;
                $id = $timesheet->getProject()->getId() . '_' . $timesheet->getActivity()->getId() . '_' . $i;
            }

            if (!\array_key_exists($id, $rows)) {
                $rows[$id] = [
                    'days' => $week,
                    'project' => $timesheet->getProject(),
                    'activity' => $timesheet->getActivity()
                ];
            }

            $rows[$id]['days'][$day]['entry'] = $timesheet;
        }

        ksort($rows);

        // attach recent activities
        $amount = $this->configuration->getQuickEntriesRecentAmount();
        $startFrom = null;
        $takeOverWeeks = $this->configuration->find('quick_entry.recent_activity_weeks');
        if ($takeOverWeeks !== null && \intval($takeOverWeeks) > 0) {
            $startFrom = clone $startWeek;
            $startFrom->modify(sprintf('-%s weeks', $takeOverWeeks));
        }
        $timesheets = $this->repository->getRecentActivities($user, $startFrom, $amount);
        foreach ($timesheets as $timesheet) {
            $id = $timesheet->getProject()->getId() . '_' . $timesheet->getActivity()->getId();
            if (\array_key_exists($id, $rows)) {
                continue;
            }
            $rows[$id] = [
                'days' => $week,
                'project' => $timesheet->getProject(),
                'activity' => $timesheet->getActivity()
            ];
        }

        $defaultBegin = $factory->createDateTime($this->configuration->getTimesheetDefaultBeginTime());
        $defaultHour = (int) $defaultBegin->format('H');
        $defaultMinute = (int) $defaultBegin->format('i');
        $defaultBegin->setTime($defaultHour, $defaultMinute, 0, 0);

        // fill all rows and columns to make sure we do not have missing records
        /** @var QuickEntryModel[] $models */
        $models = [];
        foreach ($rows as $id => $row) {
            $model = new QuickEntryModel($user, $row['project'], $row['activity']);
            foreach ($row['days'] as $dayId => $day) {
                if (!\array_key_exists('entry', $day)) {
                    $tmp = new Timesheet();
                    $tmp->setUser($user);
                    $tmp->setProject($row['project']);
                    $tmp->setActivity($row['activity']);
                    $tmp->setBegin(clone $day['day']);
                    $tmp->getBegin()->setTime($defaultHour, $defaultMinute, 0, 0);
                    $model->addTimesheet($tmp);
                } else {
                    $model->addTimesheet($day['entry']);
                }
            }
            $models[] = $model;
        }

        // create prototype model
        $empty = new QuickEntryModel($user);
        foreach ($week as $dayId => $day) {
            $tmp = new Timesheet();
            $tmp->setUser($user);
            $tmp->setBegin(clone $day['day']);
            $tmp->getBegin()->setTime($defaultHour, $defaultMinute, 0, 0);
            $empty->addTimesheet($tmp);
        }

        // add empty rows for simpler starting
        $minRows = \intval($this->configuration->find('quick_entry.minimum_rows'));
        if (\count($models) < $minRows) {
            $newRows = $minRows - \count($models);
            for ($a = 0; $a < $newRows; $a++) {
                $model = new QuickEntryModel();
                foreach ($week as $dayId => $day) {
                    $tmp = new Timesheet();
                    $tmp->setUser($user);
                    $tmp->setBegin(clone $day['day']);
                    $tmp->getBegin()->setTime($defaultHour, $defaultMinute, 0, 0);
                    $model->addTimesheet($tmp);
                }

                $models[] = $model;
            }
        }

        // sort rows by projects - make it configurable in the future
        uasort($models, [$this, 'sortByProjectName']);

        $formModel = new QuickEntryWeek($startWeek, $models);

        $form = $this->createForm(QuickEntryForm::class, $formModel, [
            'timezone' => $this->getDateTimeFactory()->getTimezone()->getName(),
            'prototype_data' => $empty,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var QuickEntryWeek $data */
            $data = $form->getData();

            $saveTimesheets = [];
            $deleteTimesheets = [];

            foreach ($data->getRows() as $tmpModel) {
                foreach ($tmpModel->getTimesheets() as $timesheet) {
                    if ($timesheet->getId() !== null) {
                        if ($timesheet->getDuration(false) === null || $timesheet->getEnd() === null) {
                            $deleteTimesheets[] = $timesheet;
                        } else {
                            $saveTimesheets[] = $timesheet;
                        }
                    } else {
                        if ($timesheet->getDuration() !== null) {
                            $saveTimesheets[] = $timesheet;
                        }
                    }
                }
            }

            try {
                $saved = false;
                if (\count($deleteTimesheets) > 0 && $this->isGranted('delete_own_timesheet')) {
                    $this->timesheetService->deleteMultipleTimesheets($deleteTimesheets);
                    $saved = true;
                }

                if (\count($saveTimesheets) > 0) {
                    $this->timesheetService->updateMultipleTimesheets($saveTimesheets);
                    $saved = true;
                }

                if ($saved) {
                    $this->flashSuccess('action.update.success');

                    return $this->redirectToRoute('quick_entry', ['begin' => $begin->format('Y-m-d')]);
                }
            } catch (\Exception $ex) {
                $this->flashError('action.update.error');
                $this->logException($ex);
            }
        }

        $page = new PageSetup('quick_entry.title');
        $page->setHelp('weekly-times.html');

        return $this->render('quick-entry/index.html.twig', [
            'page_setup' => $page,
            'days' => $week,
            'form' => $form->createView(),
        ]);
    }

    private function sortByProjectName(QuickEntryModel $a, QuickEntryModel $b): int
    {
        $aName = $a->getProject()?->getName();
        $bName = $b->getProject()?->getName();

        if ($aName === null || $bName === null) {
            return -1;
        }

        return strcmp($aName, $bName);
    }
}

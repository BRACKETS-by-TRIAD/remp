<?php

namespace Remp\MailerModule\Presenters;

use Remp\MailerModule\Repository\ListsRepository;
use Remp\MailerModule\Repository\BatchesRepository;
use Remp\MailerModule\Repository\TemplatesRepository;
use Remp\MailerModule\Repository\BatchTemplatesRepository;
use Faker\Provider\tr_TR\DateTime;

final class DashboardPresenter extends BasePresenter
{
    private $batchTemplatesRepository;
    
    private $templatesRepository;
    
    private $batchesRepository;

    private $listsRepository;

    public function __construct(
        BatchTemplatesRepository $batchTemplatesRepository,
        TemplatesRepository $templatesRepository,
        BatchesRepository $batchesRepository,
        ListsRepository $listsRepository
    ) {
        parent::__construct();

        $this->batchTemplatesRepository = $batchTemplatesRepository;
        $this->templatesRepository = $templatesRepository;
        $this->batchesRepository = $batchesRepository;
        $this->listsRepository = $listsRepository;
    }

    public function renderDefault()
    {
        $ct = 0;
        $labels = [];
        $numOfDays = 30;
        $typeDataSets = [];
        $now = new \DateTime();
        $from = (new \DateTime())->sub(new \DateInterval('P' . $numOfDays . 'D'));

        // fill graph columns
        for ($i = $numOfDays; $i > 0; $i--) {
            $labels[] = date("d. m. Y", strtotime('-' . $i . ' days'));
        }

        $allMailTypes = $this->listsRepository->all();

        // fill datasets meta info
        foreach ($allMailTypes as $mailType) {
            $typeDataSets[$mailType->id] = [
                'id' => $mailType->id,
                'label' => $mailType->title,
                'data' => array_fill(0, $numOfDays, 0),
                'fill' => true,
                'backgroundColor' => '#FDECB7',
                'strokeColor' => '#FDECB7',
                'borderColor' => '#FDECB7',
                'lineColor' => '#FDECB7',
                'count' => 0
            ];
        }

        $allSentMailsData = $this->batchTemplatesRepository->getDashboardGraphData($from, $now);

        $allSentEmailsDataSet = [
            'data' => array_fill(0, $numOfDays, 0),
            'fill' => true,
            'backgroundColor' => '#FDECB7',
            'strokeColor' => '#FDECB7',
            'borderColor' => '#FDECB7',
            'lineColor' => '#FDECB7',
            'count' => 0
        ];

        // parse all sent mails data to chart.js format
        foreach ($allSentMailsData as $row) {
            $allSentEmailsDataSet['data'][array_search(
                $row->first_email_sent_at->format('d. m. Y'),
                $labels
            )] = $row->sent_mails;
        }

        $typesData = $this->batchTemplatesRepository->getDashboardGraphDataForTypes($from, $now);

        // parse sent mails by type data to chart.js format
        foreach ($typesData as $row) {
            $typeDataSets[$row->mail_type_id]['count'] += $row->sent_mails;

            $typeDataSets[$row->mail_type_id]['data'][
                array_search(
                    $row->first_email_sent_at->format('d. m. Y'),
                    $labels
                )
            ] = $row->sent_mails;
        }

        // order sets by sent count
        usort($typeDataSets, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        // remove sets with zero sent count
        $typeDataSets = array_filter($typeDataSets, function ($a) {
            return ($a['count'] == 0) ? null : $a;
        });


        $inProgressBatches = $this->batchesRepository->getInProgressBatches(10);
        $lastDoneBatches = $this->batchesRepository->getLastDoneBatches(10);

        $this->template->allSentEmailsDataSet = $allSentEmailsDataSet;
        $this->template->typeDataSets = array_values($typeDataSets);
        $this->template->inProgressBatches = $inProgressBatches;
        $this->template->lastDoneBatches = $lastDoneBatches;
        $this->template->labels = $labels;
    }

    public function renderDetail($id)
    {
        $labels = [];
        $dataSet = [];
        $numOfDays = 30;
        $now = new \DateTime();
        $from = (new \DateTime())->sub(new \DateInterval('P' . $numOfDays . 'D'));

        // fill graph columns
        for ($i = $numOfDays; $i > 0; $i--) {
            $labels[] = date("d. m. Y", strtotime('-' . $i . ' days'));
        }

        $mailType = $this->listsRepository->find($id);

        $dataSet = [
            'label' => $mailType->title,
            'data' => array_fill(0, $numOfDays, 0),
            'fill' => false,
            'borderColor' => 'rgb(75, 192, 192)',
            'lineTension' => 0.5
        ];

        $data = $this->batchTemplatesRepository->getDashboardDetailGraphData($id, $from, $now)->fetchAll();

        // parse sent mails by type data to chart.js format
        foreach ($data as $row) {
            $dataSet['data'][
                array_search(
                    $row->first_email_sent_at->format('d. m. Y'),
                    $labels
                )
            ] = $row->sent_mails;
        }

        $this->template->dataSet = $dataSet;
        $this->template->labels = $labels;
    }
}

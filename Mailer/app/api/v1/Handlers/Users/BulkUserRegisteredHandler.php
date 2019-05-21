<?php

namespace Remp\MailerModule\Api\v1\Handlers\Users;

use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Nette\Database\Table\ActiveRow;
use Remp\MailerModule\Repository\ListsRepository;
use Remp\MailerModule\Repository\UserSubscriptionsRepository;
use Remp\MailerModule\User\IUser;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\InputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;

class BulkUserRegisteredHandler extends BaseHandler
{
    private $userSubscriptionsRepository;

    private $userProvider;

    private $listsRepository;

    public function __construct(
        UserSubscriptionsRepository $userSubscriptionsRepository,
        ListsRepository $listsRepository,
        IUser $userProvider
    ) {
        parent::__construct();
        $this->userSubscriptionsRepository = $userSubscriptionsRepository;
        $this->userProvider = $userProvider;
        $this->listsRepository = $listsRepository;
    }

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_POST_RAW, 'raw')
        ];
    }

    public function handle($params)
    {
        try {
            $data = Json::decode($params['raw'], Json::FORCE_ARRAY);
        } catch (JsonException $e) {
            return new JsonApiResponse(400, ['status' => 'error', 'message' => 'Input data was not valid JSON.']);
        }

        $errors = [];
        $iteration = -1;
        $users = [];

        foreach ($data as $item) {
            $iteration++;

            // process email
            if (!isset($item['email'])) {
                $errors = array_merge($errors, ["element_" . $iteration => 'Required field missing: email.']);
                continue;
            }
            if (!empty($this->userSubscriptionsRepository->findByEmail($item['email']))) {
                continue;
            }

            // process user_id
            if (!isset($item['user_id'])) {
                $errors = array_merge($errors, ["element_" . $iteration => 'Required field missing: user_id.']);
                continue;
            }
            
            $users[] = $item;
        }

        if (!empty($errors)) {
            return new JsonApiResponse(400, [
                'status' => 'error',
                'message' => 'Input data contains errors. See included list of errors.',
                'errors' => $errors,
            ]);
        }

        $lists = $this->listsRepository->all();
        foreach ($users as $user) {
            /** @var ActiveRow $list */
            foreach ($lists as $list) {
                if ($list->auto_subscribe) {
                    $this->userSubscriptionsRepository->subscribeUser($list, $user['user_id'], $user['email']);
                } else {
                    $this->userSubscriptionsRepository->unsubscribeUser($list, $user['user_id'], $user['email']);
                }
            }
        }

        return new JsonApiResponse(200, ['status' => 'ok']);
    }
}

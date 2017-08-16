<?php

namespace Remp\MailerModule\Repository;

use Remp\MailerModule\Repository;
use Nette\Database\Table\IRow;

class JobQueueRepository extends Repository
{
    protected $tableName = 'mail_job_queue';

    const STATUS_NEW = 'new';
    const STATUS_DONE = 'done';
    const STATUS_ERROR = 'error';

    public function add(IRow $batch, $mailTemplateId, $email, $sorting)
    {
        return $this->insert([
            'mail_batch_id' => $batch->id,
            'mail_template_id' => $mailTemplateId,
            'status' => JobQueueRepository::STATUS_NEW,
            'sorting' => $sorting,
            'email' => $email,
        ]);
    }

    public function multiInsert($rows)
    {
        $values = [];
        $status = JobQueueRepository::STATUS_NEW;
        foreach ($rows as $row) {
            $email = addslashes($row['email']);
            $values[] = "({$row['batch']},{$row['templateId']},'{$status}',{$row['sorting']},'{$email}')";
        }
        $values = implode(',', $values);
        $this->database->query("INSERT INTO {$this->tableName} (mail_batch_id, mail_template_id, status, sorting, email) VALUES $values");
    }

    public function clearBatch($batch)
    {
        $this->getTable()->where(['mail_batch_id' => $batch->id])->delete();
    }

    public function stripEmails($batch, $leaveEmails)
    {
        if (!$leaveEmails) {
            return;
        }

        $total = $this->getTable()->where(['mail_batch_id' => $batch->id])->count('*');
        $remove = $total - $leaveEmails;
        if ($remove > 0) {
            $this->getDatabase()->query("DELETE FROM {$this->tableName} WHERE mail_batch_id={$batch->id} ORDER BY RAND() LIMIT $remove");
        }
    }

    public function removeAlreadySent(IRow $batch)
    {
        $job = $batch->job;
        $this->getDatabase()->query("DELETE FROM {$this->tableName} WHERE mail_batch_id={$batch->id} AND email IN (SELECT email FROM mail_logs WHERE mail_job_id = {$job->id})");
    }

    public function removeAlreadyQueued(IRow $batch)
    {
        $job = $batch->job;
        $this->getDatabase()->query("DELETE mjq1.* FROM mail_job_queue mjq1 JOIN mail_job_queue mjq2 ON mjq1.email = mjq2.email
  WHERE mjq1.mail_batch_id = {$batch->id} AND mjq2.mail_batch_id IN (SELECT id FROM mail_job_batch WHERE mail_job_id = {$job->id} AND id <> {$batch->id})
");
    }

    public function removeUnsubscribed(IRow $batch)
    {
        $this->getDatabase()->query("DELETE FROM mail_job_queue WHERE mail_job_queue.mail_batch_id = {$batch->id} AND mail_job_queue.email IN (
  SELECT users.email FROM users
  INNER JOIN mail_user_subscriptions ON mail_user_subscriptions.user_id = users.id AND subscribed=0
  WHERE mail_user_subscriptions.mail_type_id IN (SELECT mail_types.id FROM mail_types INNER JOIN mail_templates ON mail_templates.mail_type_id = mail_types.id WHERE mail_templates.id = mail_job_queue.mail_template_id)
)");
    }

    public function removeOtherVariants(IRow $batch, $variantId)
    {
        $this->getDatabase()->query("DELETE FROM mail_job_queue WHERE mail_job_queue.mail_batch_id = {$batch->id} AND mail_job_queue.email IN (
  SELECT users.email FROM users
  INNER JOIN mail_user_subscriptions ON mail_user_subscriptions.user_id = users.id AND subscribed=1 AND (mail_user_subscriptions.mail_type_variant_id != {$variantId} OR mail_user_subscriptions.mail_type_variant_id IS NULL)
  WHERE mail_user_subscriptions.mail_type_id IN (SELECT mail_types.id FROM mail_types INNER JOIN mail_templates ON mail_templates.mail_type_id = mail_types.id WHERE mail_templates.id = mail_job_queue.mail_template_id)
)");
    }

    public function getBatchEmails(IRow $mailBatch, $lastId, $count = null)
    {
        $selection = $this->getTable()->where(['id > ?' => $lastId, 'mail_batch_id' => $mailBatch->id])->order('id ASC');
        if ($count !== null) {
            $selection->limit($count);
        }

        return $selection;
    }

    public function getJob($email, $batchId)
    {
        return $this->getTable()->where(['email' => $email, 'mail_batch_id' => $batchId])->limit(1)->fetch();
    }

    public function deleteJobsByBatch($batchId, $newOnly = false)
    {
        $table = $this->getTable()->where(['mail_batch_id' => $batchId]);
        if ($newOnly) {
            $table->where(['status' => 'new']);
        }

        return $table->delete();
    }
}

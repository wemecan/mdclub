<?php

declare(strict_types=1);

namespace MDClub\Service\Topic;

use MDClub\Traits\Brandable;

/**
 * 删除话题
 */
class Delete extends Abstracts
{
    use Brandable;

    /**
     * 软删除话题
     *
     * @param  int  $topicId
     */
    public function delete(int $topicId): void
    {
        $this->model->delete($topicId);
    }

    /**
     * 批量软删除话题
     *
     * @param  array $topicIds
     */
    public function deleteMultiple(array $topicIds): void
    {
        if (!$topicIds) {
            return;
        }

        $this->model->delete($topicIds);
    }

    /**
     * 恢复话题
     *
     * @param  int  $topicId
     */
    public function restore(int $topicId): void
    {
        $this->model->restore($topicId);
    }

    /**
     * 批量恢复话题
     *
     * @param array $topicIds
     */
    public function restoreMultiple(array $topicIds): void
    {
        if (!$topicIds) {
            return;
        }

        $this->model->restore($topicIds);
    }

    /**
     * 硬删除话题
     *
     * @param int $topicId
     */
    public function destroy(int $topicId): void
    {
        $this->destroyMultiple([$topicId]);
    }

    /**
     * 批量硬删除话题
     *
     * @param array $topicIds
     */
    public function destroyMultiple(array $topicIds): void
    {
        if (!$topicIds) {
            return;
        }

        $topics = $this->model
            ->onlyTrashed()
            ->field(['topic_id', 'cover'])
            ->select($topicIds);

        if (!$topics) {
            return;
        }

        $topicIds = array_column($topics, 'topic_id');
        $this->model->force()->delete($topicIds);

        // 查询关注了这些话题的用户ID
        $followerIds = $this->followModel
            ->where('followable_type', 'topic')
            ->where('followable_id', $topicIds)
            ->pluck('user_id');

        // 每个用户关注的话题数量
        $userTopicCount = [];
        foreach ($followerIds as $followerId) {
            isset($userTopicCount[$followerId])
                ? $userTopicCount[$followerId] += 1
                : $userTopicCount[$followerId] = 1;
        }

        // 减少用户的 following_topic_count
        foreach ($userTopicCount as $followerId => $count) {
            $this->userModel
                ->where('user_id', $followerId)
                ->dec('following_topic_count', $count)
                ->update();
        }

        // 删除关注关系
        $this->followModel
            ->where('followable_type', 'topic')
            ->where('followable_id', $topicIds)
            ->delete();

        // 删除话题关系
        $this->topicableModel
            ->where('topic_id', $topicIds)
            ->delete();

        // 删除封面图片
        foreach ($topics as $topic) {
            $this->deleteImage($topic['topic_id'], $topic['cover']);
        }
    }
}

<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\mongodb\debug;

use Yii;
use yii\debug\models\search\Db;
use yii\debug\panels\DbPanel;
use yii\log\Logger;

/**
 * MongoDbPanel panel that collects and displays MongoDB queries performed.
 *
 * @property array $profileLogs This property is read-only.
 *
 * @author Klimov Paul <klimov@zfort.com>
 * @since 2.0.1
 */
class MongoDbPanel extends DbPanel
{
    /**
     * {@inheritdoc}
     */
    public $db = 'mongodb';


    /**
     * {@inheritdoc}
     */
    public function init()
    {
        $this->actions['mongodb-explain'] = [
            'class' => 'yii\\mongodb\\debug\\ExplainAction',
            'panel' => $this,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'MongoDB';
    }

    /**
     * {@inheritdoc}
     */
    public function getSummaryName()
    {
        return 'MongoDB';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetail()
    {
        $searchModel = new Db();

        if (!$searchModel->load(Yii::$app->request->getQueryParams())) {
            $searchModel->load($this->defaultFilter, '');
        }

        $dataProvider = $searchModel->search($this->getModels());
        $dataProvider->getSort()->defaultOrder = $this->defaultOrder;

        return Yii::$app->view->render('@yii/mongodb/debug/views/detail', [
            'panel' => $this,
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
        ]);
    }

    /**
     * Returns all profile logs of the current request for this panel.
     * @return array
     */
    public function getProfileLogs()
    {
        $target = $this->module->logTarget;

        return $target->filterMessages($target->messages, Logger::LEVEL_PROFILE, [
            'yii\mongodb\Command::*',
            'yii\mongodb\Query::*',
            'yii\mongodb\BatchQueryResult::*',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function hasExplain()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function getQueryType($timing)
    {
        $timing = ltrim($timing);
        $timing = mb_substr($timing, 0, mb_strpos($timing, '('), 'utf8');
        $matches = explode('.', $timing);

        return count($matches) ? array_pop($matches) : '';
    }

    /**
     * {@inheritdoc}
     */
    public static function canBeExplained($type)
    {
        return $type === 'find';
    }
}

<?php
/* @var $panel yii\mongodb\debug\MongoDbPanel */
/* @var $searchModel yii\debug\models\search\Db */
/* @var $dataProvider yii\data\ArrayDataProvider */

use yii\helpers\Html;
use yii\grid\GridView;
use yii\web\View;

echo Html::tag('h1', $panel->getName() . ' Queries');

echo GridView::widget([
    'dataProvider' => $dataProvider,
    'id' => 'db-panel-detailed-grid',
    'options' => ['class' => 'detail-grid-view table-responsive'],
    'filterModel' => $searchModel,
    'filterUrl' => $panel->getUrl(),
    'columns' => [
        [
            'attribute' => 'seq',
            'label' => 'Time',
            'value' => function ($data) {
                $timeInSeconds = $data['timestamp'] / 1000;
                $millisecondsDiff = (int) (($timeInSeconds - floor($timeInSeconds)) * 1000);

                return date('H:i:s.', floor($timeInSeconds)) . sprintf('%03d', $millisecondsDiff);
            },
            'headerOptions' => [
                'class' => 'sort-numerical'
            ]
        ],
        [
            'attribute' => 'duration',
            'value' => function ($data) {
                return sprintf('%.1f ms', $data['duration']);
            },
            'options' => [
                'width' => '10%',
            ],
            'headerOptions' => [
                'class' => 'sort-numerical'
            ]
        ],
        [
            'attribute' => 'type',
            'value' => function ($data) {
                return Html::encode($data['type']);
            },
            'filter' => $panel->getTypes(),
        ],
        [
            'attribute' => 'query',
            'value' => function ($data) use ($panel) {
                $query = Html::encode($data['query']);

                if (!empty($data['trace'])) {
                    $query .= Html::ul($data['trace'], [
                        'class' => 'trace',
                        'item' => function ($trace) use ($panel) {
                            return '<li>' . $panel->getTraceLine($trace) . '</li>';
                        },
                    ]);
                }

                if ($panel->canBeExplained($data['type'])) {
                    $query .= Html::tag('p', '', ['class' => 'db-explain-text']);

                    $query .= Html::tag(
                        'div',
                        Html::a('[+] Explain', (['mongodb-explain', 'seq' => $data['seq'], 'tag' => Yii::$app->controller->summary['tag']])),
                        ['class' => 'db-explain']
                    );
                }

                return $query;
            },
            'format' => 'raw',
            'options' => [
                'width' => '60%',
            ],
        ]
    ],
]);

echo Html::tag(
    'div',
    Html::a('[+] Explain all', '#'),
    ['id' => 'db-explain-all']
);

$this->registerJs('debug_db_detail();', View::POS_READY);
?>

<script>
    function debug_db_detail() {
        $('.db-explain a').on('click', function(e) {
            e.preventDefault();

            var $explain = $('.db-explain-text', $(this).parent().parent());

            if ($explain.is(':visible')) {
                $explain.hide();
                $(this).text('[+] Explain');
            } else {
                $explain.load($(this).attr('href')).show();
                $(this).text('[-] Explain');
            }
        });

        $('#db-explain-all a').on('click', function(e) {
            e.preventDefault();

            $('.db-explain a').click();
        });
    }
</script>

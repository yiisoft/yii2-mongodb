<?php

namespace yiiunit\extensions\mongodb;

use yii\mongodb\Query;

class QueryTest extends TestCase
{
    public function testSelect()
    {
        // default
        $query = new Query();
        $select = [];
        $query->select($select);
        $this->assertEquals($select, $query->select);

        $query = new Query();
        $select = ['name', 'something'];
        $query->select($select);
        $this->assertEquals($select, $query->select);
    }

    public function testFrom()
    {
        $query = new Query();
        $from = 'customer';
        $query->from($from);
        $this->assertEquals($from, $query->from);

        $query = new Query();
        $from = ['', 'customer'];
        $query->from($from);
        $this->assertEquals($from, $query->from);
    }

    public function testWhere()
    {
        $query = new Query();
        $query->where(['name' => 'name1']);
        $this->assertEquals(['name' => 'name1'], $query->where);

        $query->andWhere(['address' => 'address1']);
        $this->assertEquals(
            [
                'and',
                ['name' => 'name1'],
                ['address' => 'address1']
            ],
            $query->where
        );

        $query->orWhere(['name' => 'name2']);
        $this->assertEquals(
            [
                'or',
                [
                    'and',
                    ['name' => 'name1'],
                    ['address' => 'address1']
                ],
                ['name' => 'name2']

            ],
            $query->where
        );
    }

    public function testFilterWhere()
    {
        // should work with hash format
        $query = new Query();
        $query->filterWhere([
            'id' => 0,
            'title' => '   ',
            'author_ids' => [],
        ]);
        $this->assertEquals(['id' => 0], $query->where);

        $query->andFilterWhere(['status' => null]);
        $this->assertEquals(['id' => 0], $query->where);

        $query->orFilterWhere(['name' => '']);
        $this->assertEquals(['id' => 0], $query->where);
    }

    public function testOrder()
    {
        $query = new Query();
        $query->orderBy('team');
        $this->assertEquals(['team' => SORT_ASC], $query->orderBy);

        $query->addOrderBy('company');
        $this->assertEquals(['team' => SORT_ASC, 'company' => SORT_ASC], $query->orderBy);

        $query->addOrderBy('age');
        $this->assertEquals(['team' => SORT_ASC, 'company' => SORT_ASC, 'age' => SORT_ASC], $query->orderBy);

        $query->addOrderBy(['age' => SORT_DESC]);
        $this->assertEquals(['team' => SORT_ASC, 'company' => SORT_ASC, 'age' => SORT_DESC], $query->orderBy);

        $query->addOrderBy('age ASC, company DESC');
        $this->assertEquals(['team' => SORT_ASC, 'company' => SORT_DESC, 'age' => SORT_ASC], $query->orderBy);
    }

    public function testLimitOffset()
    {
        $query = new Query();
        $query->limit(10)->offset(5);
        $this->assertEquals(10, $query->limit);
        $this->assertEquals(5, $query->offset);
    }

    public function testOptions()
    {
        $query = new Query();
        $options = [
            '$comment' => 'test comment',
            '$min' => ['ts' => 10],
        ];
        $query->options($options);
        $this->assertEquals($options, $query->options);

        $newComment = 'new comment';
        $query->addOptions(['$comment' => $newComment]);
        $this->assertEquals($newComment, $query->options['$comment']);
    }

    /**
     * @depends testFilterWhere
     */
    public function testAndFilterCompare()
    {
        $query = new Query();

        $result = $query->andFilterCompare('name', null);
        $this->assertInstanceOf('yii\mongodb\Query', $result);
        $this->assertNull($query->where);

        $query->andFilterCompare('name', '');
        $this->assertNull($query->where);

        $query->andFilterCompare('name', 'John Doe');
        $condition = ['=', 'name', 'John Doe'];
        $this->assertEquals($condition, $query->where);

        $condition = ['and', $condition, ['like', 'name', 'Doe']];
        $query->andFilterCompare('name', 'Doe', 'like');
        $this->assertEquals($condition, $query->where);

        $condition = ['and', $condition, ['>', 'rating', '9']];
        $query->andFilterCompare('rating', '>9');
        $this->assertEquals($condition, $query->where);

        $condition = ['and', $condition, ['<=', 'value', '100']];
        $query->andFilterCompare('value', '<=100');
        $this->assertEquals($condition, $query->where);
    }
}

<?php

namespace Sigma\Document\Action;

use Sigma\Contract\Action;

class Get implements Action
{
    public function prepare($index, $id)
    {
        dump($index);
        die();
        $params = [
            'index' => $index,
            'id'    => $id
        ];

        return $params;
    }

    public function execute(Elasticsearch $elasticsearch, array $params)
    {
        $elasticsearch->get($params);
    }
}

<?php

class ProductService { 

    private $dataService;

    public function __construct($dataService) {
        $this->dataService = $dataService;
    }

    public function getProducts($method, $batchSize = 0, $startingIndex = 0)
    {
        $filter['method'] = $method;
        $filter['limit'] = $batchSize;
        $filter['offset'] = $startingIndex;

        $products = $this->dataService->getProducts($filter);  

        return array( 've_products' => $products );
    }    
}    

<?php
interface DataServiceInterface
{
    public function getProducts($filters);
    public function createProductSyncTable();
    public function deleteProductSyncTable();
    public function storeUpdatedProduct($productId, $unmarkDeleted = false);
    public function storeDeletedProduct($productId, $deleteChildren = true);
}
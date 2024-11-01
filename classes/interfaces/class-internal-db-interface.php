<?php
interface DatabaseServiceInterface
{
    public function createTable();
    public function dropTable();
    public function updateTable($updateArray, $updateCondition);
    public function insertTable($insertArray);
    public function selectFromTable($whereCondition = "", $orderByCondition = "", $limitCondition = "");
    public function deleteFromTable($deleteArray);
}
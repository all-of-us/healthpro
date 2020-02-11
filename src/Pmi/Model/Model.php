<?php

namespace Pmi\Model;


class Model
{
    protected $app;

    protected $repository;

    protected $table;

    public function __construct($app)
    {
        $this->app = $app;
        $this->repository = $app['em']->getRepository($this->table);
    }

    public function insert($data)
    {
        return $this->repository->insert($data);
    }

    public function update($id, $data)
    {
        return $this->repository->update($id, $data);
    }

    public function delete($id)
    {
        return $this->repository->insert($id);
    }

    public function fetchOneBy($where)
    {
        return $this->repository->fetchOneBy($where);
    }

    public function fetchBy($where, $order = [], $limit = null)
    {
        return $this->repository->fetchBy($where, $order, $limit);
    }

    public function wrapInTransaction($callback)
    {
        return $this->repository->wrapInTransaction($callback);
    }

}

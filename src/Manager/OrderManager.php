<?php

namespace App\Manager;

use App\Document\ShopOrder;
use App\Repository\ShopOrderRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

const CART_SESSION_ID = "cart_id";

class OrderManager
{
    private SessionInterface $session;
    private ShopOrderRepository $repository;

    public function __construct(SessionInterface $session, ShopOrderRepository $repository)
    {
        $this->session = $session;
        $this->repository = $repository;
    }

    public function getCurrent(): ShopOrder
    {
        $orderId = $this->session->get(CART_SESSION_ID);
        if (!$orderId)
            return $this->create();

        $order = $this->repository->getById($orderId);
        if (!$order)
            return $this->create();

        return $order;
    }

    public function create(): ShopOrder
    {
        $order = new ShopOrder();
        $this->repository->getDocumentManager()->persist($order);
        $this->repository->getDocumentManager()->flush();
        $this->session->set(CART_SESSION_ID, $order->getId());
        return $order;
    }
}
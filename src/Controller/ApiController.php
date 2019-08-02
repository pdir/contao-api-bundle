<?php

namespace Pdir\ApiBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Movie;
use App\Form\MovieType;

/**
 * Movie controller.
 * @Route("/rest", name="rest_")
 */
class ApiController extends FOSRestController
{
    /**
     * Lists all news.
     * @Rest\Get("/news")
     *
     * @return Response
     */
    public function getNewsAction()
    {
        $repository = $this->getDoctrine()->getRepository(News::class);
        $news = $repository->findall();
        return $this->handleView($this->view($news));
    }

    /**
     * Get news by url.
     * @Rest\Get("/news/:url")
     *
     * @return Response
     */
    public function getNewsByUrlAction($url)
    {
        $repository = $this->getDoctrine()->getRepository(News::class);
        $news = $repository->findOneBy($url);
        return $this->handleView($this->view($news));
    }
}

<?php

namespace Pdir\ApiBundle\Controller;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\NewsModel;
use Contao\ArticleModel;
use Contao\ContentModel;
use Contao\PageModel;
use Contao\Backend;
use Contao\Frontend;
use Contao\FilesModel;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/rest",defaults={"_format": "json","_token_check"=false})
 */
class ApiController extends Controller
{
    protected $framework;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * The field whitelist for allowed fields.
     *
     * @var string
     */
    protected $whitelist;

    public function __construct(ContainerInterface $container, ContaoFramework $framework)
    {
        if ($container->getParameter('rest_api_disabled')) {
            die('Contao Rest API is disabled');
        }

        $this->whitelist = $container->getParameter('rest_api_allowed_fields');

        $this->container = $container;
        $this->framework = $framework;

        $this->framework->initialize();
    }

    /**
     * @return Response
     *
     * @Route("/newsme", name="rest_news", methods={"GET"})
     */
    public function newsAction()
    {
        $this->framework->initialize();

        $objNewsModel = NULL;
        try {
            $objNewsModel = NewsModel::findAll();
        } catch (\Exception $exception) {
            return new JsonResponse(['error' => 'The resource news returns no items']);
        }

        if($objNewsModel !== null)
        {
            while($elem = $objNewsModel->next()){
                $news[] = $elem;
            }

        }
        return new JsonResponse(['news' => $news]);
    }

    /**
     * Get news by Slug
     *
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @Route("/news", name="rest_news_slug", methods={"GET"})
     */
    public function newsBySlugAction(Request $request) // NewsRepository $newsRepository
    {
        $this->framework->initialize();

        $slug = $request->query->get('slug');

        if (!$slug) {
            return null;
        }

        /** @var Model $adapter */
        $adapter = $this->framework->getAdapter(NewsModel::class);

        $options = [];

        if (0 < ($limit = (int) $request->query->get('limit'))) {
            $options['limit'] = $limit;
        }
        if (0 < ($offset = (int) $request->query->get('offset'))) {
            $options['offset'] = $offset;
        }

        $columns = [];
        $columns[] = ['alias' => $slug];

        /** @var Model $model */
        $objNews = $adapter->findByIdOrAlias($slug, $options);

        if(null === $objNews)
        {
            return new JsonResponse(['error' => 'The resource news returns no items']);
        }

        $news = $objNews->row();

        // prepare elements
        $news['elements'] = $this->getPageElements($news['id'], 'tl_news');

        if (null === $news) {
            echo 'Keine Artikel gefunden.';
            return new JsonResponse(['news' => 'No news was found.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['news' => $news], Response::HTTP_OK);
    }

    /**
     * Get all pages
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @Route("/page", name="rest_page", methods={"GET"})
     */
    public function pageAction(Request $request)
    {
        $this->framework->initialize();

        $slug = $request->query->get('slug');

        $page = null;

        /** @var PageModel $adapter */
        $adapter = $this->framework->getAdapter(PageModel::class);
        if($slug == '/')
        {
            /** @var Frontend $frontend */
            $frontend = $this->framework->getAdapter(Frontend::class);
            $objPage = $adapter->findFirstPublishedByPid($frontend->getRootPageFromUrl()->id);
        }
        else
        {
            $objPage = $adapter->findPublishedByIdOrAlias($slug);
        }

        if(null !== $objPage)
        {
            $page = $objPage->row();
        }

        // prepare articles
        $objArticles = ArticleModel::findByPid($objPage->id);

        if(null !== $objArticles)
        {
            $articles = [];
            foreach ($objArticles as $article)
            {
                $arr = $article->row();

                //var_dump($arr);

                if($arr['singleSRC'])
                {
                    $arr['singleSRC'] = $arr['singleSRC'] ? $this->getImageData($arr['singleSRC'], $arr['size'], $arr['imagemargin']) : null;
                }

                if($multiSrc = $arr['multiSRC'])
                {
                    $arr['multiSRC'] = null;
                    foreach($multiSrc as $uuid)
                    {
                        $arr['multiSRC'][] = $this->getImageData($uuid, $arr['size'], $arr['imagemargin']);
                    }

                }

                if($orderSrc = $arr['orderSRC'])
                {
                    $arr['orderSRC'] = null;
                    foreach($orderSrc as $uuid)
                    {
                        $arr['orderSRC'][] = $this->getImageData($uuid, $arr['size'], $arr['imagemargin']);
                    }

                }

                $arr['elements'] = $this->getPageElements($arr['id'], 'tl_article');

                // headline
                if($arr['headline']) {
                    $arr['headline'] = unserialize($arr['headline']);
                }

                // css id
                if($arr['cssID']) {
                    $arr['cssID'] = unserialize($arr['cssID']);
                }

                // fullsize
                $arr['fullSize'] = intval($arr['fullsize']);

                $articles[] = $this->prepareData($arr);
            }

            $page['articles'] = $articles;
        }


        // prepare elements
        $page['elements'] = $this->getPageElements($page['id'], 'tl_article');

        if($slug)
        {
            return $this->getJson('page', $this->prepareData($page));
        }

        return new JsonResponse($adapter->findAll(), Response::HTTP_OK);
    }

    /**
     * Get page by Slug
     * @param $slug
     * @param Request $request
     * @return JsonResponse
     *
     * @Route("/pageme/{slug}", name="rest_page_slug", methods={"GET"})
     */
    public function pageBySlugAction($slug, Request $request)
    {
        $this->framework->initialize();

        if (!$slug) {
            return null;
        }
        /** @var NewsModel $adapter */
        $adapter = $this->framework->getAdapter(PageModel::class);

        $options = [];

        if (0 < ($limit = (int) $request->query->get('limit'))) {
            $options['limit'] = $limit;
        }
        if (0 < ($offset = (int) $request->query->get('offset'))) {
            $options['offset'] = $offset;
        }

        return new Response(json_encode($adapter->findByIdOrAlias($slug, $options)));
    }

    /**
     * Get sitemap by domain
     *
     * @return JsonResponse
     *
     * @Route("/sitemap", name="rest_sitemap", methods={"GET"})
     */
    public function sitemapAction()
    {
        $this->framework->initialize();

        /** @var Backend $adapter */
        $adapter = $this->framework->getAdapter(Backend::class);

        $objRootPage = \Frontend::getRootPageFromUrl();

        return new JsonResponse(['sitemap' => $this->findSearchablePages($objRootPage->id)], Response::HTTP_OK);
    }

    /**
     * Get all searchable pages and return them as array
     *
     * @param integer $pid
     * @param string  $domain
     * @param boolean $blnIsSitemap
     *
     * @return array
     */
    public static function findSearchablePages($pid=0, $domain='', $blnIsSitemap=false)
    {
        $objPages = \PageModel::findPublishedByPid($pid, array('ignoreFePreview'=>true));
        if ($objPages === null)
        {
            return [];
        }
        $arrPages = [];

        // Recursively walk through all subpages
        foreach ($objPages as $objPage)
        {
            if ($objPage->type == 'regular')
            {
                // Searchable and not protected
                if ((!$objPage->noSearch || $blnIsSitemap) && (!$objPage->protected || (\Config::get('indexProtected') && (!$blnIsSitemap || $objPage->sitemap == 'map_always'))) && (!$blnIsSitemap || $objPage->sitemap != 'map_never') && !$objPage->requireItem)
                {
                    // var_dump($objPage);
                    $arrPages[] = [
                        'id' => $objPage->id,
                        'url' => $objPage->alias, // $objPage->getAbsoluteUrl(),
                        'hide' => $objPage->hide,
                        'published' => $objPage->published,
                        'mainTitle' => $objPage->mainPageTitle ? $objPage->mainPageTitle : $objPage->title
                    ];
                    // Get articles with teaser
                    if (($objArticles = \ArticleModel::findPublishedWithTeaserByPid($objPage->id, array('ignoreFePreview'=>true))) !== null)
                    {
                        foreach ($objArticles as $objArticle)
                        {
                            $arrPages[] = [
                                'id' => $objArticle->id,
                                'url' => $objArticle->alias, // $objPage->getAbsoluteUrl('/articles/' . ($objArticle->alias ?: $objArticle->id)),
                                'hide' => $objArticle->hide,
                                'published' => $objArticle->published,
                                'mainTitle' => $objArticle->mainPageTitle ? $objArticle->mainPageTitle : $objArticle->title
                            ];
                        }
                    }
                }
            }

            // Get subpages
            if ((!$objPage->protected || \Config::get('indexProtected')) && ($arrSubpages = static::findSearchablePages($objPage->id, $domain, $blnIsSitemap)))
            {
                $arrPages = array_merge($arrPages, $arrSubpages);
            }
        }
        return $arrPages;
    }

    /**
     * get content of page elements
     *
     * @var $id integer
     * @var $table string
     * @return array
     */
    private function getPageElements($pid, $table = null)
    {
        /** @var Model $adapter */
        $adapter = $this->framework->getAdapter(ContentModel::class);

        /** @var Model $content */
        $content = $adapter->findPublishedByPidAndTable($pid, $table);

        $elements = [];

        if(null !== $content)
        {
            while ($content->next())
            {
                $row = $content->row();

                if($row['singleSRC'])
                {

                    $row['singleSRC'] = $this->getImageData($row['singleSRC'], $row['size'], $row['imagemargin']);
                }

                if($multiSrc = $row['multiSRC'])
                {
                    $row['multiSRC'] = [];
                    foreach($multiSrc as $uuid)
                    {
                        $row['multiSRC'][] = $this->getImageData($uuid, $row['size'], $row['imagemargin']);
                    }

                }

                if($orderSrc = $row['orderSRC'])
                {
                    $row['orderSRC'] = [];
                    foreach($orderSrc as $uuid)
                    {
                        $row['orderSRC'][] = $this->getImageData($uuid, $row['size'], $row['imagemargin']);
                    }

                }

                $row['html'] = \Contao\Controller::getContentElement($content->id);

                // headline
                if($row['headline']) {
                    $row['headline'] = unserialize($row['headline']);
                }

                // css id
                if($row['cssID']) {
                    $row['cssID'] = unserialize($row['cssID']);
                }

                // replace insert tags
                $row = $this->replaceInsertTags($row);

                // load modules
                if($row['type'] === 'module')
                {
                    // echo "<pre>"; print_r($row);
                }

                // prepare data
                $row = $this->prepareData($row);

                $elements[] = $row;
            }
        }

        return $elements;
    }

    /**
     * prepare data for output
     *
     * @param $key string
     * @param $data array
     * @return JsonResponse
     */
    private function getJson($key, $data)
    {
        return new JsonResponse([$key => $data], Response::HTTP_OK);
    }

    private function getImagePath($uuid)
    {
        $file = FilesModel::findByUuid($uuid);

        if(null !== $file)
        {
            return $file->path;
        }

        return null;
    }

    /**
     * prepare data for output
     *
     * @param $data array
     * @return array
     */
    private function prepareData($data)
    {
        // whitelist check not needed
        if ('all' == $this->whitelist) {
            return $data;
        }

        // check data against whitelist fields
        $data = array_intersect_key( $data, array_flip( explode(',', $this->whitelist) ) );

        return $data;
    }

    private function getImageData($uuid, $size = null, $imageMargin = null)
    {
        if(!$uuid)
        {
            return null;
        }

        if(!$file = \FilesModel::findByUuid($uuid))
        {
            return null;
        }

        return [
            'path' => $file->path,
            'meta' => unserialize($file->meta),
            'name' => $file->name,
            'size' => [
                'width' => unserialize($size)[0] ? intval(unserialize($size)[0]) : null,
                'height' => unserialize($size)[1] ? intval(unserialize($size)[1]) : null,
                'set' => unserialize($size)[2] ? : null
            ],
            'imageMargin' => unserialize($imageMargin),
            'relations' => unserialize($file->arrRelations),
            'importantPart' => [
                'importantPartWidth' => intval($file->importantPartWidth) ? : null,
                'importantPartHeight' => intval($file->importantPartHeight) ? : null,
                'importantPartX' => intval($file->importantPartX) ? : null,
                'importantPartY' => intval($file->importantPartY) ? : null
            ]
        ];
    }

    private function replaceInsertTags($arr)
    {
        // Replace insert Tags
        $arr['imageUrl'] = \Contao\Controller::replaceInsertTags($arr['imageUrl']);
        $arr['html'] = \Contao\Controller::replaceInsertTags($arr['html']);
        $arr['text'] = \Contao\Controller::replaceInsertTags($arr['text']);

        return $arr;
    }
}

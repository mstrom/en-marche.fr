<?php

namespace Test\AppBundle\Sitemap;

use AppBundle\Entity\Article;
use AppBundle\Entity\ArticleCategory;
use AppBundle\Entity\OrderArticle;
use AppBundle\Repository\ArticleCategoryRepository;
use AppBundle\Repository\ArticleRepository;
use AppBundle\Repository\OrderArticleRepository;
use AppBundle\Sitemap\SitemapFactory;
use Doctrine\Common\Persistence\ObjectManager;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Tackk\Cartographer\Sitemap;

class SitemapFactoryTest extends TestCase
{
    private $objectManager;
    private $routerInterface;
    private $cacheItemPoolInterface;
    private $article;
    private $category;

    public function testAddArticle()
    {
        $sitemap = new Sitemap();
        $this->invokeReflectionMethod('addArticles', $sitemap);

        $this->assertEquals(1, $sitemap->getUrlCount());
    }

    public function testCreateContentSitemapWithoutHit()
    {
        $cacheItemInterface = $this->createMock(CacheItemInterface::class);
        $cacheItemInterface->expects($this->any())
            ->method('isHit')
            ->willReturn(true);

        $cacheItemInterface->expects($this->any())
            ->method('get')
            ->willReturn((string) new Sitemap());

        $this->cacheItemPoolInterface->expects($this->any())
            ->method('getItem')
            ->willReturn($cacheItemInterface);
        $this->cacheItemPoolInterface->expects($this->never())
            ->method('save');

        $this->invokeReflectionMethod('createContentSitemap');
    }

    public function testCreateContentSitemapWithHit()
    {
        $cacheItemInterface = $this->createMock(CacheItemInterface::class);
        $cacheItemInterface->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $cacheItemInterface->expects($this->once())
            ->method('set');
        $cacheItemInterface->expects($this->any())
            ->method('get')
            ->willReturn((string) new Sitemap());

        $this->cacheItemPoolInterface->method('getItem')
            ->willReturn($cacheItemInterface);
        $this->cacheItemPoolInterface->expects($this->once())
            ->method('save')
            ->with($cacheItemInterface);

        $this->invokeReflectionMethod('createContentSitemap');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->category = new ArticleCategory();
        $this->category->setSlug('category');

        $this->article = new Article();
        $this->article->setSlug('article');
        $this->article->setCategory($this->category);
        $this->article->setUpdatedAt(new \DateTime());

        $this->objectManager = $this->createMock(ObjectManager::class);
        $this->routerInterface = $this->createMock(RouterInterface::class);
        $this->cacheItemPoolInterface = $this->createMock(CacheItemPoolInterface::class);

        $categoriesRepository = $this->createMock(ArticleCategoryRepository::class);
        $categoriesRepository->expects($this->any())
            ->method('findAll')
            ->willReturn([$this->category]);

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->expects($this->any())
            ->method('findAllPublished')
            ->willReturn([$this->article]);

        $orderArticleRepository = $this->createMock(OrderArticleRepository::class);
        $orderArticleRepository->expects($this->any())
            ->method('findAllPublished')
            ->willReturn([]);

        $this->objectManager->method('getRepository')
            ->will($this->returnValueMap([
                [ArticleCategory::class, $categoriesRepository],
                [Article::class, $articleRepository],
                [OrderArticle::class, $orderArticleRepository],
            ])
        );
    }

    protected function tearDown()
    {
        $this->objectManager = null;
        $this->routerInterface = null;
        $this->cacheItemPoolInterface = null;
        $this->article = null;
        $this->category = null;

        parent::tearDown();
    }

    private function invokeReflectionMethod(string $methodName, Sitemap $sitemap = null)
    {
        $reflection = new \ReflectionMethod(SitemapFactory::class, $methodName);
        $reflection->setAccessible(true);

        return $reflection->invoke(new SitemapFactory($this->objectManager, $this->routerInterface, $this->cacheItemPoolInterface), $sitemap);
    }
}

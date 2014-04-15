<?php
namespace Topxia\Service\Article\Impl;

use Topxia\Service\Common\BaseService;
use Topxia\Service\Article\ArticleService;
use Topxia\Service\Article\Type\ArticleTypeFactory;
use Topxia\Common\ArrayToolkit;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Image\ImageInterface;
use Symfony\Component\HttpFoundation\File\File;

class ArticleServiceImpl extends BaseService implements ArticleService
{
	public function getArticle($id)
	{
		return $this->getArticleDao()->getArticle($id);
	}

	public function getArticleByAlias($alias)
	{
		return ArticleSerialize::unserialize($this->getArticleDao()->getArticleByAlias($alias));
	}

	public function findArticlesByCategoryIds(array $categoryIds, $start, $limit)
	{
		return $this->getArticleDao()->findArticlesByCategoryIds($categoryIds, $start, $limit);
	}

	public function findArticlesCount(array $categoryIds)
	{
		return $this->getArticleDao()->findArticlesCount($categoryIds);
	}

	public function searchArticles(array $conditions, $sort, $start, $limit)
	{	
		$orderBys = $this->filterSort($sort);

		$conditions = $this->prepareSearchConditions($conditions);

		return $this->getArticleDao()->searchArticles($conditions, $orderBys, $start, $limit);
	}

	public function searchArticlesCount($conditions)
	{	
		$conditions = $this->prepareSearchConditions($conditions);

		return $this->getArticleDao()->searchArticlesCount($conditions);
	}

	private function prepareSearchConditions($conditions)
	{
		$conditions = array_filter($conditions);

		if (isset($conditions['includeChindren']) && $conditions['includeChindren'] == true) {
			if (isset($conditions['categoryId'])) {
			$childrenIds = $this->getCategoryService()->findCategoryChildrenIds($conditions['categoryId']);
			$conditions['categoryIds'] = array_merge(array($conditions['categoryId']), $childrenIds);
			unset($conditions['categoryId']);
			unset($conditions['includeChindren']);
			}
		} 

		return $conditions;
	}

	public function createArticle($article)
	{
		if(empty($article)){
			$this->createServiceException("文章内容为空，创建文章失败！");
		}
		$tagNames = array_filter(explode(',', $article['tags']));
        $tags = $this->getTagService()->findTagsByNames($tagNames);
        $tagIdsArray = ArrayToolkit::column($tags, 'id');
        $article['tagIds'] = implode(',', $tagIdsArray);

        $new_article = array();
		$match = preg_match('/<img.+src=\"?(.+\.(jpg|gif|bmp|bnp|png))\"?.+>/i', $article['richeditorBody'], $matches);
		
		$new_article['picture'] = $match ? $matches[1] : "null";
		$new_article['thumb'] = $article['thumb'];
		$new_article['originalThumb'] = $article['originalThumb'];
		$new_article['title'] = $article['title'];
		$new_article['body'] = $article['richeditorBody'];
		$new_article['featured'] = empty($article['featured']) ? 0 : 1;
		$new_article['promoted'] = empty($article['promoted']) ? 0 : 1;
		$new_article['sticky'] = empty($article['sticky']) ? 0 : 1;
		$new_article['tagIds'] = $article['tagIds'];
		$new_article['categoryId'] = $article['categoryId'];
		$new_article['source'] = $article['source'];
		$new_article['sourceUrl'] = $article['sourceUrl'];
		$new_article['publishedTime'] = strtotime($article['publishedTime']);
		$new_article['createdTime'] = time();
		$new_article['updated'] = time();
		$new_article['userId'] = $this->getCurrentUser()->id;

		$article = $this->getArticleDao()->addArticle($new_article);
		$this->getLogService()->info('Article', 'create', "创建文章《({$article['title']})》({$article['id']})", $article);
		
		return $article;
	}

	public function updateArticle($id,$article)
	{
		$checkArticle = $this->getArticle($id);

		if(empty($checkArticle)){
			throw $this->createServiceException("文章不存在，操作失败。");
		}
		$tagNames = array_filter(explode(',', $article['tags']));
        $tags = $this->getTagService()->findTagsByNames($tagNames);
        $tagIdsArray = ArrayToolkit::column($tags, 'id');
        $article['tagIds'] = implode(',', $tagIdsArray);
        
        $edit_article = array();
		$match = preg_match('/<img.+src=\"?(.+\.(jpg|gif|bmp|bnp|png))\"?.+>/i', $article['richeditorBody'], $matches);
		
		$edit_article['picture'] = $match ? $matches[1] : "null";
		$edit_article['thumb'] = $article['thumb'];
		$edit_article['originalThumb'] = $article['originalThumb'];
		$edit_article['title'] = $article['title'];
		$edit_article['body'] = $article['richeditorBody'];
		$edit_article['featured'] = empty($article['featured']) ? 0 : 1;
		$edit_article['promoted'] = empty($article['promoted']) ? 0 : 1;
		$edit_article['sticky'] = empty($article['sticky']) ? 0 : 1;
		$edit_article['tagIds'] = $article['tagIds'];
		$edit_article['categoryId'] = $article['categoryId'];
		$edit_article['source'] = $article['source'];
		$edit_article['sourceUrl'] = $article['sourceUrl'];
		$edit_article['publishedTime'] = strtotime($article['publishedTime']);
		$edit_article['createdTime'] = time();
		$edit_article['updated'] = time();
		$edit_article['userId'] = $this->getCurrentUser()->id;
		// $edit_article['picture'] = $article['picture'];
		$article = $this->getArticleDao()->updateArticle($id,$edit_article);

		$this->getLogService()->info('Article', 'update', "修改文章《({$article['title']})》({$article['id']})", $article);
		
		return $article;
	}

	public function hitArticle($id)
	{
		$this->getArticleDao()->waveArticle($id, 'hits', +1);
	}

	public function updateArticleProperty($id, $property)
	{
		$article = $this->getArticleDao()->getArticle($id);
		if(empty($property)){
			throw $this->createServiceException('属性{$property}不存在，更新失败！');
		}

		if($article){
			if($article[$property] == 1){
				$property_val = 0;
				$this->getArticleDao()->updateArticle($id,array("{$property}"=>$property_val));
			}else{
				$property_val = 1;
				$this->getArticleDao()->updateArticle($id,array("{$property}"=>$property_val));
			}
		}
		$this->getLogService()->info('Article', 'updateArticleProperty', "文章#{$id},$article[$property]=>{$property_val}");
		return $property_val;
	}

	public function trashArticle($id)
	{
		$this->getArticleDao()->updateArticle($id, $fields = array('status' => 'trash'));
		$this->getLogService()->info('Article', 'trash', "文章#{$id}移动到回收站");
	}

	public function deleteArticle($id)
	{
		$this->getArticleDao()->deleteArticle($id);
		$this->getLogService()->info('Article', 'delete', "文章#{$id}永久删除");
	}

	public function deleteArticlesByIds($ids)
	{
		$id_log = "";
		if(count($ids) == 1){
			$this->getArticleDao()->deleteArticle($ids[0]);
		}else{
			foreach ($ids as $id) {
				$this->getArticleDao()->deleteArticle($id);
			}
		}
		
		$this->getLogService()->info('Article', 'delete', "文章#{$id_log}永久删除");
	}

	public function publishArticle($id)
	{
		$this->getArticleDao()->updateArticle($id, $fields = array('status' => 'published'));
		$this->getLogService()->info('Article', 'publish', "文章#{$id}发布");
	}

	public function unpublishArticle($id)
	{
		$this->getArticleDao()->updateArticle($id, $fields = array('status' => 'unpublished'));
		$this->getLogService()->info('Article', 'unpublish', "文章#{$id}未发布");
	}

	public function isAliasAvaliable($alias)
	{
		if (empty($alias)) {
			return true;
		}
		$Article = $this->getArticleDao()->getArticleByAlias($alias);
		return $Article ? false : true;
	}

	public function changeIndexPicture($filePath, $options)
	{
        $pathinfo = pathinfo($filePath);
        $imagine = new Imagine();
        $rawImage = $imagine->open($filePath);
        $largeImage = $rawImage->copy();


        $largeImage->crop(new Point($options['x'], $options['y']), new Box($options['width'], $options['height']));
        $largeImage->resize(new Box(230, 115));
        $largeFilePath = "{$pathinfo['dirname']}/{$pathinfo['filename']}_large.{$pathinfo['extension']}";
        $largeImage->save($largeFilePath, array('quality' => 90));

         $largeFilePath = "{$pathinfo['dirname']}/{$pathinfo['filename']}_large.{$pathinfo['extension']}";

        $largeFileRecord = $this->getFileService()->uploadFile('article', new File($largeFilePath));

		//move yuantu
		$uri = $largeFileRecord['uri'];
		$file_original_name = basename($uri);
		$file_original_extension = pathinfo($uri,PATHINFO_EXTENSION);

		$file_original_name_new = str_replace(".{$file_original_extension}", "_orig.{$file_original_extension}", $file_original_name);
		$file_original_path = str_replace(array('public://',"{$file_original_name}"),'', $uri);
		$file_original_directory =$pathinfo['dirname'] . '/' . $file_original_path;
		$file_original_directory = str_replace("/tmp", "", $file_original_directory);
		$file_original_directory = substr($file_original_directory, 0,-1);
		
		$new_file = new File($filePath);
		$file_res = $new_file->move($file_original_directory, $file_original_name_new);

        @unlink($filePath);

        $webPath = realpath($this->getKernel()->getParameter('topxia.upload.public_directory')."/../");

		return array(
				'file_original_name'=>$file_original_name,
				'file_original_name_new'=>$file_original_name_new,
				'file_original_path'=>str_replace($webPath, "", $file_original_directory)
			);
	}

	private function filterSort($sort)
	{
		switch ($sort) {

			case 'created':
				$orderBys = array(
					array('createdTime', 'DESC'),
				);
				break;

			case 'published':
				$orderBys = array(
					array('sticky', 'DESC'),
					array('publishedTime', 'DESC'),
				);
				break;

			case 'normal':
				$orderBys = array(
					array('publishedTime', 'DESC'),
				);
				break;

			case 'popular':
				$orderBys = array(
					array('hits', 'DESC'),
				);
				break;

			default:
				throw $this->createServiceException('参数sort不正确。');
		}
		return $orderBys;
	}

	private function getArticleDao()
	{
		return $this->createDao('Article.ArticleDao');
	}

    private function getCategoryService()
    {
        return $this->createService('Article.CategoryService');
    }

    private function getLogService()
    {
        return $this->createService('System.LogService');
    }

    private function getFileService()
    {
        return $this->createService('Content.FileService');
    }

    private function getTagService()
    {
        return $this->createService('Taxonomy.TagService');
    }
}


class ArticleSerialize
{
    public static function serialize(array &$course)
    {
    	if (isset($course['tagIds'])) {
    		if (is_array($course['tagIds']) and !empty($course['tagIds'])) {
    			$course['tagIds'] = '|' . implode('|', $course['tagIds']) . '|';
    		} else {
    			$course['tagIds'] = '';
    		}
    	}
        return $course;
    }

    public static function unserialize(array $course = null)
    {
    	if (empty($course)) {
    		return $course;
    	}

		$course['tagIds'] = empty($course['tagIds']) ? array() : explode('|', trim($course['tagIds'], '|'));

		return $course;
    }

    public static function unserializes(array $courses)
    {
    	return array_map(function($course) {
    		return ArticleSerialize::unserialize($course);
    	}, $courses);
    }
}
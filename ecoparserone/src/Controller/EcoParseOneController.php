<?php

namespace Drupal\ecoparseone\Controller;

use GuzzleHttp\Client;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;

class EcoParseOneController extends ControllerBase {

    public function checkNewArticles() {
        \Drupal::messenger()->addMessage($this->t('Parsing has started.'));

        $client = new Client();

        $sourceUrl = $this->config('ecoparseone.settings')->get('source_url');
        $articlesLimit = $this->config('ecoparseone.settings')->get('articles_limit');
        $selectedContentType = $this->config('ecoparseone.settings')->get('content_type');
        $xpathQuery = $this->config('ecoparseone.settings')->get('xpath_articles');

        $parsedUrl = parse_url($sourceUrl);
        $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];

        $response = $client->request('GET', $sourceUrl);
        $html = $response->getBody()->getContents();

        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $articles = $xpath->query($xpathQuery);

        $articlesToProcess = [];

        $counter = 0;
        foreach ($articles as $article) {
            if ($counter >= $articlesLimit) {
                break;
            }
            $title = $article->nodeValue;
            $link = $article->getAttribute('href');
            if (!preg_match('~^(?:f|ht)tps?://~i', $link)) {
                $link = rtrim($baseUrl, '/') . '/' . ltrim($link, '/');
            }

            $query = \Drupal::entityQuery('node')
                ->condition('type', $selectedContentType)
                ->condition('field_import_title', $title)
                ->range(0, 1);
            $result = $query->execute();

            if (empty($result)) {
                $articlesToProcess[] = ['title' => $title, 'link' => $link];
            }

            $counter++;
        }

        $this->processArticle($articlesToProcess);

        $uniqueArticlesCount = count($articlesToProcess);
        \Drupal::messenger()->addMessage($this->t('Number of unique articles to process: @count', ['@count' => $uniqueArticlesCount]));

        return count($articlesToProcess);
    }

    private function processArticle($articlesToProcess) {
        $client = new Client();
        $createdArticlesCount = 0;
        $xpathArticleContent = $this->config('ecoparseone.settings')->get('xpath_article_content');

        foreach ($articlesToProcess as $articleData) {
            $title = $articleData['title'];
            $relativeLink = $articleData['link'];

            $response = $client->request('GET', $relativeLink);
            $articleHtml = $response->getBody()->getContents();

            $articleDom = new \DOMDocument();
            @$articleDom->loadHTML($articleHtml);
            $articleXpath = new \DOMXPath($articleDom);
            $articleContentNodes = $articleXpath->query($xpathArticleContent);

            $articleContent = '';
            foreach ($articleContentNodes as $node) {
                $articleContent .= trim($node->textContent) . "\n";
            }

            $selectedContentType = $this->config('ecoparseone.settings')->get('content_type');
            $selectedTextFormat = $this->config('ecoparseone.settings')->get('text_format');
            $publishedStatus = $this->config('ecoparseone.settings')->get('published_status');

            $node = Node::create([
                'type'  => $selectedContentType,
                'title' => $articleData['title'],
                'field_import_title' => $articleData['title'],
                'body'  => [
                    'value'  => $articleContent,
                    'format' => $selectedTextFormat,
                ],
                'status' => $publishedStatus,
            ]);

            if ($node->save()) {
                $createdArticlesCount++;
                \Drupal::messenger()->addMessage($this->t('Created page: @title', ['@title' => $articleData['title']]));
            }
        }

        \Drupal::messenger()->addMessage($this->t('Total number of articles created: @count', ['@count' => $createdArticlesCount]));
    }

}

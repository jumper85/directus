<?php

use Directus\Hook\Payload;
use HtmlSanitizer\Extension\Basic\BasicExtension;
use HtmlSanitizer\Extension\Code\CodeExtension;
use HtmlSanitizer\Extension\ExtensionInterface;
use HtmlSanitizer\Model\Cursor;
use HtmlSanitizer\Node\NodeInterface;
use HtmlSanitizer\SanitizerInterface;
use HtmlSanitizer\Visitor\AbstractNodeVisitor;
use HtmlSanitizer\Visitor\HasChildrenNodeVisitorTrait;
use HtmlSanitizer\Visitor\NamedNodeVisitorInterface;

class MarkNode extends HtmlSanitizer\Node\AbstractTagNode
{
    use HtmlSanitizer\Node\HasChildrenTrait;

    public function getTagName(): string
    {
        return 'mark';
    }
}

class MarkVisitor extends AbstractNodeVisitor implements NamedNodeVisitorInterface
{
    use HasChildrenNodeVisitorTrait;

    protected function getDomNodeName(): string
    {
        return 'mark';
    }

    public function getDefaultAllowedAttributes(): array
    {
        return [
            'class'
        ];
    }

    public function getDefaultConfiguration(): array
    {
        return [
            'custom_config' => null,
        ];
    }

    protected function createNode(\DOMNode $domNode, Cursor $cursor): NodeInterface
    {
        $node = new MarkNode($cursor->node);

        return $node;
    }
}

class MarkExtension implements ExtensionInterface
{
    public function getName(): string
    {
        return 'mark';
    }

    public function createNodeVisitors(array $config = []): array
    {
        return [
            'mark' => new MarkVisitor($config['tags']['mark'] ?? []),
        ];
    }
}

/**
 * Try to get extension from content, if it fails, we know data is invalid
 *
 * @param $data
 *
 * @return bool|string
 */
function retrieveExtension($data)
{
    $imageContents = base64_decode($data);

    if ($imageContents === false) {
        return false;
    }

    $validExtensions = ['png', 'jpeg', 'jpg', 'gif'];

    $contentType = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $imageContents);

    if (substr($contentType, 0, 5) !== 'image') {
        return false;
    }

    $extension = ltrim($contentType, 'image/');

    if (!in_array(strtolower($extension), $validExtensions)) {
        return false;
    }

    return $extension;
}

/**
 * Generates the sanitizer with configuration
 *
 * @return SanitizerInterface
 */
function getSanitizer(): SanitizerInterface
{
    $builder = new HtmlSanitizer\SanitizerBuilder();

    $builder->registerExtension(new BasicExtension());
    $builder->registerExtension(new CodeExtension());
    $builder->registerExtension(new MarkExtension());

    return $builder->build(
        [
            'extensions' => ['basic', 'code', 'mark'],
            'tags'       => [
                'code' => [
                    'allowed_attributes' => ['class'],
                ],
            ],
        ]
    );
}

function sanitize(string $content): string {
    $content = json_decode($content, true);

    $sanitizer = getSanitizer();

    foreach ($content['blocks'] as $index => $blockInfo) {
        switch ($blockInfo['type']) {
            case 'image':
                $dataUri = $blockInfo['data']['file']['url'];
                $encodedImage = array_pop(explode(',', $dataUri));

                if (false === retrieveExtension($encodedImage)) {
                    $dataUri = '';
                }

                $blockInfo['data']['file']['url'] = $dataUri;
                break;
            case 'paragraph':
                $blockInfo['data']['text'] = $sanitizer->sanitize($blockInfo['data']['text']);
                break;
        }

        $content['blocks'][$index] = $blockInfo;
    }

    return json_encode($content);
}

return [
    'filters' => [
        'item.create.page:before' => function (Payload $payload) {
            if ($payload->get('content')) {
                $payload->set('content', sanitize($payload->get('content')));
            }
            return $payload;
        },
        'item.update.page:before' => function (Payload $payload) {
            if ($payload->get('content')) {
                $payload->set('content', sanitize($payload->get('content')));
            }

            return $payload;
        }
    ]
];
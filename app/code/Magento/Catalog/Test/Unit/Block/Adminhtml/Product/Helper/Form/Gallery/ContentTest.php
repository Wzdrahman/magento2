<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Catalog\Test\Unit\Block\Adminhtml\Product\Helper\Form\Gallery;

use Magento\Catalog\Block\Adminhtml\Product\Helper\Form\Gallery;
use Magento\Catalog\Block\Adminhtml\Product\Helper\Form\Gallery\Content;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Entity\Attribute;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Media\Config;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\Read;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Json\EncoderInterface;
use Magento\Framework\Phrase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\MediaStorage\Helper\File\Storage\Database;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ContentTest extends TestCase
{
    /**
     * @var Filesystem|MockObject
     */
    protected $fileSystemMock;

    /**
     * @var Read|MockObject
     */
    protected $readMock;

    /**
     * @var Content|MockObject
     */
    protected $content;

    /**
     * @var Config|MockObject
     */
    protected $mediaConfigMock;

    /**
     * @var EncoderInterface|MockObject
     */
    protected $jsonEncoderMock;

    /**
     * @var Gallery|MockObject
     */
    protected $galleryMock;

    /**
     * @var Image|MockObject
     */
    protected $imageHelper;

    /**
     * @var Database|MockObject
     */
    protected $databaseMock;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    protected function setUp(): void
    {
        $this->fileSystemMock = $this->getMockBuilder(Filesystem::class)
            ->addMethods(['stat'])
            ->onlyMethods(['getDirectoryRead'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->readMock = $this->createMock(ReadInterface::class);
        $this->galleryMock = $this->createMock(Gallery::class);
        $this->mediaConfigMock = $this->createPartialMock(
            Config::class,
            ['getMediaUrl', 'getMediaPath']
        );
        $this->jsonEncoderMock = $this->getMockBuilder(EncoderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->databaseMock = $this->getMockBuilder(Database::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectManager = new ObjectManager($this);
        $this->content = $this->objectManager->getObject(
            Content::class,
            [
                'mediaConfig' => $this->mediaConfigMock,
                'jsonEncoder' => $this->jsonEncoderMock,
                'filesystem' => $this->fileSystemMock,
                'fileStorageDatabase' => $this->databaseMock
            ]
        );
    }

    public function testGetImagesJson()
    {
        $url = [
            ['file_1.jpg', 'url_to_the_image/image_1.jpg'],
            ['file_2.jpg', 'url_to_the_image/image_2.jpg']
        ];
        $mediaPath = [
            ['file_1.jpg', 'catalog/product/image_1.jpg'],
            ['file_2.jpg', 'catalog/product/image_2.jpg']
        ];

        $sizeMap = [
            ['catalog/product/image_1.jpg', ['size' => 399659]],
            ['catalog/product/image_2.jpg', ['size' => 879394]]
        ];

        $imagesResult = [
            [
                'value_id' => '2',
                'file' => 'file_2.jpg',
                'media_type' => 'image',
                'position' => '0',
                'url' => 'url_to_the_image/image_2.jpg',
                'size' => 879394
            ],
            [
                'value_id' => '1',
                'file' => 'file_1.jpg',
                'media_type' => 'image',
                'position' => '1',
                'url' => 'url_to_the_image/image_1.jpg',
                'size' => 399659
            ]
        ];

        $images = [
            'images' => [
                [
                    'value_id' => '1',
                    'file' => 'file_1.jpg',
                    'media_type' => 'image',
                    'position' => '1'
                ] ,
                [
                    'value_id' => '2',
                    'file' => 'file_2.jpg',
                    'media_type' => 'image',
                    'position' => '0'
                ]
            ]
        ];

        $this->content->setElement($this->galleryMock);
        $this->galleryMock->expects($this->once())->method('getImages')->willReturn($images);
        $this->fileSystemMock->expects($this->once())->method('getDirectoryRead')->willReturn($this->readMock);

        $this->mediaConfigMock->expects($this->any())->method('getMediaUrl')->willReturnMap($url);
        $this->mediaConfigMock->expects($this->any())->method('getMediaPath')->willReturnMap($mediaPath);
        $this->readMock->expects($this->any())->method('stat')->willReturnMap($sizeMap);
        $this->jsonEncoderMock->expects($this->once())->method('encode')->willReturnCallback('json_encode');

        $this->readMock->expects($this->any())
            ->method('isFile')
            ->willReturn(true);
        $this->databaseMock->expects($this->any())
            ->method('checkDbUsage')
            ->willReturn(false);

        $this->assertSame(json_encode($imagesResult), $this->content->getImagesJson());
    }

    public function testGetImagesJsonWithoutImages()
    {
        $this->content->setElement($this->galleryMock);
        $this->galleryMock->expects($this->once())->method('getImages')->willReturn(null);

        $this->assertSame('[]', $this->content->getImagesJson());
    }

    public function testGetImagesJsonWithException()
    {
        $this->imageHelper = $this->getMockBuilder(Image::class)
            ->disableOriginalConstructor()
            ->setMethods(['getDefaultPlaceholderUrl'])
            ->getMock();

        $this->objectManager->setBackwardCompatibleProperty(
            $this->content,
            'imageHelper',
            $this->imageHelper
        );

        $placeholderUrl = 'url_to_the_placeholder/placeholder.jpg';

        $imagesResult = [
            [
                'value_id' => '2',
                'file' => 'file_2.jpg',
                'media_type' => 'image',
                'position' => '0',
                'url' => 'url_to_the_placeholder/placeholder.jpg',
                'size' => 0
            ],
            [
                'value_id' => '1',
                'file' => 'file_1.jpg',
                'media_type' => 'image',
                'position' => '1',
                'url' => 'url_to_the_placeholder/placeholder.jpg',
                'size' => 0
            ]
        ];

        $images = [
            'images' => [
                [
                    'value_id' => '1',
                    'file' => 'file_1.jpg',
                    'media_type' => 'image',
                    'position' => '1'
                ],
                [
                    'value_id' => '2',
                    'file' => 'file_2.jpg',
                    'media_type' => 'image',
                    'position' => '0'
                ]
            ]
        ];

        $this->content->setElement($this->galleryMock);
        $this->galleryMock->expects($this->once())->method('getImages')->willReturn($images);
        $this->fileSystemMock->expects($this->any())->method('getDirectoryRead')->willReturn($this->readMock);
        $this->mediaConfigMock->expects($this->any())->method('getMediaUrl');
        $this->mediaConfigMock->expects($this->any())->method('getMediaPath');

        $this->readMock->expects($this->any())
            ->method('isFile')
            ->willReturn(true);
        $this->databaseMock->expects($this->any())
            ->method('checkDbUsage')
            ->willReturn(false);

        $this->readMock->expects($this->any())->method('stat')->willReturnOnConsecutiveCalls(
            $this->throwException(
                new FileSystemException(new Phrase('test'))
            ),
            $this->throwException(
                new FileSystemException(new Phrase('test'))
            )
        );
        $this->imageHelper->expects($this->any())->method('getDefaultPlaceholderUrl')->willReturn($placeholderUrl);
        $this->jsonEncoderMock->expects($this->once())->method('encode')->willReturnCallback('json_encode');

        $this->assertSame(json_encode($imagesResult), $this->content->getImagesJson());
    }

    /**
     * Test GetImageTypes() will return value for given attribute from data persistor.
     *
     * @return void
     */
    public function testGetImageTypesFromDataPersistor()
    {
        $attributeCode = 'thumbnail';
        $value = 'testImageValue';
        $scopeLabel = 'testScopeLabel';
        $label = 'testLabel';
        $name = 'testName';
        $expectedTypes = [
            $attributeCode => [
                'code' => $attributeCode,
                'value' => $value,
                'label' => $label,
                'name' => $name,
            ],
        ];
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getData')
            ->with($this->identicalTo($attributeCode))
            ->willReturn(null);
        $mediaAttribute = $this->getMediaAttribute($label, $attributeCode);
        $product->expects($this->once())
            ->method('getMediaAttributes')
            ->willReturn([$mediaAttribute]);
        $this->galleryMock->expects($this->exactly(2))
            ->method('getDataObject')
            ->willReturn($product);
        $this->galleryMock->expects($this->once())
            ->method('getImageValue')
            ->with($this->identicalTo($attributeCode))
            ->willReturn($value);
        $this->galleryMock->expects($this->once())
            ->method('getScopeLabel')
            ->with($this->identicalTo($mediaAttribute))
            ->willReturn($scopeLabel);
        $this->galleryMock->expects($this->once())
            ->method('getAttributeFieldName')
            ->with($this->identicalTo($mediaAttribute))
            ->willReturn($name);
        $this->getImageTypesAssertions($attributeCode, $scopeLabel, $expectedTypes);
    }

    /**
     * Test GetImageTypes() will return value for given attribute from product.
     *
     * @return void
     */
    public function testGetImageTypesFromProduct()
    {
        $attributeCode = 'thumbnail';
        $value = 'testImageValue';
        $scopeLabel = 'testScopeLabel';
        $label = 'testLabel';
        $name = 'testName';
        $expectedTypes = [
            $attributeCode => [
                'code' => $attributeCode,
                'value' => $value,
                'label' => $label,
                'name' => $name,
            ],
        ];
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getData')
            ->with($this->identicalTo($attributeCode))
            ->willReturn($value);
        $mediaAttribute = $this->getMediaAttribute($label, $attributeCode);
        $product->expects($this->once())
            ->method('getMediaAttributes')
            ->willReturn([$mediaAttribute]);
        $this->galleryMock->expects($this->exactly(2))
            ->method('getDataObject')
            ->willReturn($product);
        $this->galleryMock->expects($this->never())
            ->method('getImageValue');
        $this->galleryMock->expects($this->once())
            ->method('getScopeLabel')
            ->with($this->identicalTo($mediaAttribute))
            ->willReturn($scopeLabel);
        $this->galleryMock->expects($this->once())
            ->method('getAttributeFieldName')
            ->with($this->identicalTo($mediaAttribute))
            ->willReturn($name);
        $this->getImageTypesAssertions($attributeCode, $scopeLabel, $expectedTypes);
    }

    /**
     * Perform assertions.
     *
     * @param string $attributeCode
     * @param string $scopeLabel
     * @param array $expectedTypes
     * @return void
     */
    private function getImageTypesAssertions(string $attributeCode, string $scopeLabel, array $expectedTypes)
    {
        $this->content->setElement($this->galleryMock);
        $result = $this->content->getImageTypes();
        $scope = $result[$attributeCode]['scope'];
        $this->assertSame($scopeLabel, $scope->getText());
        unset($result[$attributeCode]['scope']);
        $this->assertSame($expectedTypes, $result);
    }

    /**
     * Get media attribute mock.
     *
     * @param string $label
     * @param string $attributeCode
     * @return MockObject
     */
    private function getMediaAttribute(string $label, string $attributeCode)
    {
        $frontend = $this->getMockBuilder(Product\Attribute\Frontend\Image::class)
            ->disableOriginalConstructor()
            ->getMock();
        $frontend->expects($this->once())
            ->method('getLabel')
            ->willReturn($label);
        $mediaAttribute = $this->getMockBuilder(Attribute::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mediaAttribute->expects($this->any())
            ->method('getAttributeCode')
            ->willReturn($attributeCode);
        $mediaAttribute->expects($this->once())
            ->method('getFrontend')
            ->willReturn($frontend);

        return $mediaAttribute;
    }

    /**
     * Test GetImagesJson() calls MediaStorage functions to obtain image from DB prior to stat call
     *
     * @return void
     */
    public function testGetImagesJsonMediaStorageMode()
    {
        $images = [
            'images' => [
                [
                    'value_id' => '0',
                    'file' => 'file_1.jpg',
                    'media_type' => 'image',
                    'position' => '0'
                ]
            ]
        ];

        $mediaPath = [
            ['file_1.jpg', 'catalog/product/image_1.jpg']
        ];

        $this->content->setElement($this->galleryMock);

        $this->galleryMock->expects($this->once())
            ->method('getImages')
            ->willReturn($images);
        $this->fileSystemMock->expects($this->once())
            ->method('getDirectoryRead')
            ->willReturn($this->readMock);
        $this->mediaConfigMock->expects($this->any())
            ->method('getMediaPath')
            ->willReturnMap($mediaPath);

        $this->readMock->expects($this->any())
            ->method('isFile')
            ->willReturn(false);
        $this->databaseMock->expects($this->any())
            ->method('checkDbUsage')
            ->willReturn(true);

        $this->databaseMock->expects($this->once())
            ->method('saveFileToFilesystem')
            ->with('catalog/product/image_1.jpg');

        $this->content->getImagesJson();
    }
}

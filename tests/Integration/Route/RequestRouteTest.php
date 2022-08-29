<?php

namespace FormRelay\Request\Tests\Integration\Route;

use FormRelay\Core\Exception\FormRelayException;
use FormRelay\Core\Tests\Integration\RegistryTestTrait;
use FormRelay\Core\Tests\Integration\SubmissionTestTrait;
use FormRelay\Request\RequestInitialization;
use FormRelay\Request\RequestRouteInitialization;
use FormRelay\Request\Route\RequestRoute;
use FormRelay\Request\Tests\Spy\DataDispatcher\RequestDataDispatcherSpyInterface;
use FormRelay\Request\Tests\Spy\DataDispatcher\SpiedOnRequestDataDispatcher;
use PHPUnit\Framework\TestCase;

class RequestRouteTest extends TestCase
{
    use RegistryTestTrait;
    use SubmissionTestTrait;

    /** @var RequestRoute */
    protected $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initRegistry();
        RequestInitialization::initialize($this->registry);
        RequestRouteInitialization::initialize($this->registry);
        
        $this->initSubmission();

        $this->subject = new RequestRoute($this->registry, $this->registry->getLogger(RequestRoute::class));
    }

    protected function registerRequestDataDispatcherSpy()
    {
        $spy = $this->createMock(RequestDataDispatcherSpyInterface::class);
        $this->registry->registerDataDispatcher(SpiedOnRequestDataDispatcher::class, [$spy]);
        return $spy;
    }

    protected function configureRequest(string $ipAddress, array $cookies = [], array $headers = [])
    {
        $this->request->expects($this->any())
            ->method('getCookies')
            ->willReturn($cookies);

        $this->request->expects($this->any())
            ->method('getIpAddress')
            ->willReturn($ipAddress);

        $requestVariableMap = [];
        foreach ($headers as $name => $value) {
            $requestVariableMap[] = [$name, $value];
        }
        $this->request->expects($this->any())
            ->method('getRequestVariable')
            ->willReturnMap($requestVariableMap);
    }

    /** @test */
    public function useConfiguredUrlAndPassData()
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->addRouteConfiguration('request', [
            'enabled' => true,
            'url' => 'https://my-endpoint.tld/api/foo',
            'fields' => [
                'field_a' => 'value_a',
            ],
        ]);

        $this->configureRequest('', [], []);
        $submission = $this->getSubmission();
        
        // process context
        $this->subject->addContext($submission, $this->request, 0);
        $this->assertEmpty($submission->getContext()->getCookies());
        $this->assertEmpty($submission->getContext()->getRequestVariables());

        // process job
        $dataDispatcherSpy->expects($this->once())->method('setUrl')->with('https://my-endpoint.tld/api/foo');
        $dataDispatcherSpy->expects($this->once())->method('addCookies')->with([]);
        $dataDispatcherSpy->expects($this->once())->method('addHeaders')->with([]);
        $dataDispatcherSpy->expects($this->once())->method('send')->with(['field_a' => 'value_a']);
        $this->subject->processPass($submission, 0);
    }

    /** @test */
    public function throwExceptionWithoutConfiguredUrl()
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->addRouteConfiguration('request', [
            'enabled' => true,
            'fields' => [
                'field_a' => 'value_a',
            ],
        ]);

        $this->configureRequest('', [], []);
        $submission = $this->getSubmission();
        
        // process context
        $this->subject->addContext($submission, $this->request, 0);
        $this->assertEmpty($submission->getContext()->getCookies());
        $this->assertEmpty($submission->getContext()->getRequestVariables());

        // process job
        $this->expectException(FormRelayException::class);
        $this->subject->processPass($submission, 0);
    }
    
    // cookie functionality

    /** @test */
    public function passThroughCookiesAsPlainList()
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->addRouteConfiguration('request', [
            'enabled' => true,
            'url' => 'https://my-endpoint.tld/api/foo',
            'fields' => [
                'field_a' => 'value_a',
            ],
            'cookies' => [
                'cookie1',
                'cookie2',
                'cookie3',
                'specialCookie.*',
            ],
        ]);

        $this->configureRequest(
            '', 
            [
                'cookie1' => 'value1',
                'cookie3' => 'value3',
                'cookie4' => 'value4',
                'specialCookie5' => 'value5',
                'specialCookie6' => 'value6',
            ], 
            []
        );

        $submission = $this->getSubmission();
        
        // process context
        $this->subject->addContext($submission, $this->request, 0);
        $this->assertEquals(
            [
                'cookie1' => 'value1', 
                'cookie3' => 'value3',
                'specialCookie5' => 'value5',
                'specialCookie6' => 'value6',
            ],
            $submission->getContext()->getCookies()
        );
        $this->assertEmpty($submission->getContext()->getRequestVariables());

        // process job
        $dataDispatcherSpy->expects($this->once())->method('setUrl')->with('https://my-endpoint.tld/api/foo');
        $dataDispatcherSpy->expects($this->once())->method('addCookies')->with([
            'cookie1' => 'value1',
            'cookie3' => 'value3',
            'specialCookie5' => 'value5',
            'specialCookie6' => 'value6',
        ]);
        $dataDispatcherSpy->expects($this->once())->method('addHeaders')->with([]);
        $dataDispatcherSpy->expects($this->once())->method('send')->with(['field_a' => 'value_a']);
        $this->subject->processPass($submission, 0);
    }

    /** @test */
    public function defineCookiesWithAssocList()
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->addRouteConfiguration('request', [
            'enabled' => true,
            'url' => 'https://my-endpoint.tld/api/foo',
            'fields' => [
                'field_a' => 'value_a',
            ],
            'cookies' => [
                'cookie1' => '__PASSTHROUGH',
                'cookie2' => 'value2b',
                'cookie3' => '__PASSTHROUGH',
                'specialCookie.*' => '__PASSTHROUGH',
            ],
        ]);

        $this->configureRequest(
            '', 
            [
                'cookie1' => 'value1',
                'cookie2' => 'value2',
                'cookie4' => 'value4',
                'specialCookie5' => 'value5',
                'specialCookie6' => 'value6',
            ], 
            []
        );

        $submission = $this->getSubmission();
        
        // process context
        $this->subject->addContext($submission, $this->request, 0);
        $this->assertEquals(
            [
                'cookie1' => 'value1', 
                'specialCookie5' => 'value5',
                'specialCookie6' => 'value6',
            ],
            $submission->getContext()->getCookies()
        );
        $this->assertEmpty($submission->getContext()->getRequestVariables());

        // process job
        $dataDispatcherSpy->expects($this->once())->method('setUrl')->with('https://my-endpoint.tld/api/foo');
        $dataDispatcherSpy->expects($this->once())->method('addCookies')->with([
            'cookie1' => 'value1',
            'cookie2' => 'value2b',
            'specialCookie5' => 'value5',
            'specialCookie6' => 'value6',
        ]);
        $dataDispatcherSpy->expects($this->once())->method('addHeaders')->with([]);
        $dataDispatcherSpy->expects($this->once())->method('send')->with(['field_a' => 'value_a']);
        $this->subject->processPass($submission, 0);
    }

    /** @test */
    public function useContentResolverAsCookieValue()
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->submissionData['field_a'] = 'value_a';

        $this->addRouteConfiguration('request', [
            'enabled' => true,
            'url' => 'https://my-endpoint.tld/api/foo',
            'fields' => [
                'field_a' => ['field' => 'field_a'],
            ],
            'cookies' => [
                'cookie1' => [
                    'field' => 'field_a',
                ],
                'cookie2' => [
                    'if' => [
                        'field_a' => 'value_a',
                        'then' => '__PASSTHROUGH',
                    ],
                ],
                'cookie3' => [
                    'if' => [
                        'field_a' => 'value_b',
                        'then' => '__PASSTHROUGH',
                    ],
                ],
                'cookie4' => [
                    'if' => [
                        'field_a' => 'value_b',
                        'else' => 'value_c',
                    ],
                ],
            ],
        ]);

        $this->configureRequest(
            '', 
            [
                'cookie1' => 'value1',
                'cookie2' => 'value2',
                'cookie3' => 'value3',
                'cookie4' => 'value4',
            ], 
            []
        );

        $submission = $this->getSubmission();
        
        // process context
        $this->subject->addContext($submission, $this->request, 0);
        $this->assertEquals(
            [
                'cookie2' => 'value2',
            ],
            $submission->getContext()->getCookies()
        );
        $this->assertEmpty($submission->getContext()->getRequestVariables());

        // process job
        $dataDispatcherSpy->expects($this->once())->method('setUrl')->with('https://my-endpoint.tld/api/foo');
        $dataDispatcherSpy->expects($this->once())->method('addCookies')->with([
            'cookie1' => 'value_a',
            'cookie2' => 'value2',
            'cookie4' => 'value_c',
        ]);
        $dataDispatcherSpy->expects($this->once())->method('addHeaders')->with([]);
        $dataDispatcherSpy->expects($this->once())->method('send')->with(['field_a' => 'value_a']);
        $this->subject->processPass($submission, 0);
    }

    // header functionality

    /** @test */
    public function passThroughHeadersAsPlainList()
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->addRouteConfiguration('request', [
            'enabled' => true,
            'url' => 'https://my-endpoint.tld/api/foo',
            'fields' => [
                'field_a' => 'value_a',
            ],
            'headers' => [
                'header1',
                'header2',
                'header3',
            ],
        ]);

        $this->configureRequest(
            '',
            [],
            [
                'header1' => 'value1',
                'header3' => 'value3',
                'header4' => 'value4',
            ]
        );

        $submission = $this->getSubmission();
        
        // process context
        $this->subject->addContext($submission, $this->request, 0);
        $this->assertEmpty($submission->getContext()->getCookies());
        $this->assertEquals(
            [
                'header1' => 'value1', 
                'header3' => 'value3',
            ],
            $submission->getContext()->getRequestVariables()
        );

        // process job
        $dataDispatcherSpy->expects($this->once())->method('setUrl')->with('https://my-endpoint.tld/api/foo');
        $dataDispatcherSpy->expects($this->once())->method('addCookies')->with([]);
        $dataDispatcherSpy->expects($this->once())->method('addHeaders')->with([
            'header1' => 'value1',
            'header3' => 'value3',
        ]);
        
        $dataDispatcherSpy->expects($this->once())->method('send')->with(['field_a' => 'value_a']);
        $this->subject->processPass($submission, 0);
    }

    // /** @test */
    public function defineHeadersWithAssocList()
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->addRouteConfiguration('request', [
            'enabled' => true,
            'url' => 'https://my-endpoint.tld/api/foo',
            'fields' => [
                'field_a' => 'value_a',
            ],
            'headers' => [
                'header1' => '__PASSTHROUGH',
                'header2' => 'value2b',
                'header3' => '__PASSTHROUGH',
            ],
        ]);

        $this->configureRequest(
            '', 
            [],
            [
                'header1' => 'value1',
                'header2' => 'value2',
                'header4' => 'value4',
            ]
        );

        $submission = $this->getSubmission();
        
        // process context
        $this->subject->addContext($submission, $this->request, 0);
        $this->assertEmpty($submission->getContext()->getCookies());
        $this->assertEquals(
            [
                'header1' => 'value1', 
            ],
            $submission->getContext()->getRequestVariables()
        );

        // process job
        $dataDispatcherSpy->expects($this->once())->method('setUrl')->with('https://my-endpoint.tld/api/foo');
        $dataDispatcherSpy->expects($this->once())->method('addCookies')->with([]);
        $dataDispatcherSpy->expects($this->once())->method('addHeaders')->with([
            'header1' => 'value1',
            'header2' => 'value2b',
        ]);
        $dataDispatcherSpy->expects($this->once())->method('send')->with(['field_a' => 'value_a']);
        $this->subject->processPass($submission, 0);
    }

    /** @test */
    public function useContentResolverAsHeaderValue()
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->submissionData['field_a'] = 'value_a';

        $this->addRouteConfiguration('request', [
            'enabled' => true,
            'url' => 'https://my-endpoint.tld/api/foo',
            'fields' => [
                'field_a' => ['field' => 'field_a'],
            ],
            'headers' => [
                'header1' => [
                    'field' => 'field_a',
                ],
                'header2' => [
                    'if' => [
                        'field_a' => 'value_a',
                        'then' => '__PASSTHROUGH',
                    ],
                ],
                'header3' => [
                    'if' => [
                        'field_a' => 'value_b',
                        'then' => '__PASSTHROUGH',
                    ],
                ],
                'header4' => [
                    'if' => [
                        'field_a' => 'value_b',
                        'else' => 'value_c',
                    ],
                ],
            ],
        ]);

        $this->configureRequest(
            '', 
            [], 
            [
                'header1' => 'value1',
                'header2' => 'value2',
                'header3' => 'value3',
                'header4' => 'value4',
            ]
        );

        $submission = $this->getSubmission();
        
        // process context
        $this->subject->addContext($submission, $this->request, 0);
        $this->assertEmpty($submission->getContext()->getCookies());
        $this->assertEquals(
            [
                'header2' => 'value2',
            ],
            $submission->getContext()->getRequestVariables()
        );

        // process job
        $dataDispatcherSpy->expects($this->once())->method('setUrl')->with('https://my-endpoint.tld/api/foo');
        $dataDispatcherSpy->expects($this->once())->method('addCookies')->with([]);
        $dataDispatcherSpy->expects($this->once())->method('addHeaders')->with([
            'header1' => 'value_a',
            'header2' => 'value2',
            'header4' => 'value_c',
        ]);
        $dataDispatcherSpy->expects($this->once())->method('send')->with(['field_a' => 'value_a']);
        $this->subject->processPass($submission, 0);
    }

    /** @test */
    public function useInternalHeaderNames()
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->submissionData['field_a'] = 'value_a';

        $this->addRouteConfiguration('request', [
            'enabled' => true,
            'url' => 'https://my-endpoint.tld/api/foo',
            'fields' => [
                'field_a' => ['field' => 'field_a'],
            ],
            'headers' => [
                'Custom-Header',
                'User-Agent',
                'Content-Type',
            ],
        ]);

        $this->configureRequest(
            '', 
            [], 
            [
                'Custom-Header' => 'value1',
                'HTTP_USER_AGENT' => 'value2',
                'CONTENT_TYPE' => 'value3',
            ]
        );

        $submission = $this->getSubmission();
        
        // process context
        $this->subject->addContext($submission, $this->request, 0);
        $this->assertEmpty($submission->getContext()->getCookies());
        $this->assertEquals(
            [
                'Custom-Header' => 'value1',
                'HTTP_USER_AGENT' => 'value2',
                'CONTENT_TYPE' => 'value3',
            ],
            $submission->getContext()->getRequestVariables()
        );

        // process job
        $dataDispatcherSpy->expects($this->once())->method('setUrl')->with('https://my-endpoint.tld/api/foo');
        $dataDispatcherSpy->expects($this->once())->method('addCookies')->with([]);
        $dataDispatcherSpy->expects($this->once())->method('addHeaders')->with([
            'Custom-Header' => 'value1',
            'User-Agent' => 'value2',
            'Content-Type' => 'value3',
        ]);
        $dataDispatcherSpy->expects($this->once())->method('send')->with(['field_a' => 'value_a']);
        $this->subject->processPass($submission, 0);
    }
}

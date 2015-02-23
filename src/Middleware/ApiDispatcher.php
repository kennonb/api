<?php namespace Dingo\Api\Middleware;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Route;

class ApiDispatcher
{

    protected $container;
    protected $currentVersion;
    protected $currentFormat;


    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     * @return mixed
     */
    public function handle( $request, Closure $next )
    {
        $this->container = new Container;

        list( $version, $format ) = $this->parseAcceptHeader( $request );

        $this->currentVersion = $version;
        $this->currentFormat  = $format;

        $route = app()->router->getCurrentRoute();

        $this->revise( $route );

        return $next( $request );
    }


    /**
     * Revise a controller route by updating the protection and scopes.
     *
     * @param Route $route
     * @return Route
     */
    public function revise( Route $route )
    {
        if ($this->routingToController( $route )) {
            list( $class, $method ) = explode( '@', $route->getActionName() );

            $controller = $this->resolveController( $class );

            try {
                $this->reviseProtection( $route, $controller, $method );
                $this->reviseScopes( $route, $controller, $method );
            } catch ( BadMethodCallException $exception ) {
                // This controller does not utilize the trait.
            }
        }

        return $route;
    }


    /**
     * Determine if the route is routing to a controller.
     *
     * @param Route $route
     * @return bool
     */
    protected function routingToController( Route $route )
    {
        return is_string( array_get( $route->getAction(), 'controller' ) );
    }


    /**
     * Revise the scopes of a controller method.
     *
     * Scopes defined on the controller are merged with those in the route definition.
     *
     * @param Route      $route
     * @param Controller $controller
     * @param string     $method
     *
     * @internal param \Dingo\Api\Routing\Route $action
     */
    protected function reviseScopes( Route $route, $controller, $method )
    {
        $properties = $controller->getProperties();

        if (isset( $properties[ '*' ][ 'scopes' ] )) {
            $route->addScopes( $properties[ '*' ][ 'scopes' ] );
        }

        if (isset( $properties[ $method ][ 'scopes' ] )) {
            $route->addScopes( $properties[ $method ][ 'scopes' ] );
        }
    }


    /**
     * Revise the protected state of a controller method.
     *
     * @param Route      $route
     * @param Controller $controller
     * @param string     $method
     *
     * @internal param Route $action
     */
    protected function reviseProtection( Route $route, $controller, $method )
    {
        $properties = $controller->getProperties();

        if (isset( $properties[ '*' ][ 'protected' ] )) {
            $route->setProtected( $properties[ '*' ][ 'protected' ] );
        }

        if (isset( $properties[ $method ][ 'protected' ] )) {
            $route->setProtected( $properties[ $method ][ 'protected' ] );
        }
    }


    /**
     * Resolve a controller from the container.
     *
     * @param string $class
     *
     * @return Controller
     */
    protected function resolveController( $class )
    {
        $controller = $this->container->make( $class );

        if ( ! $this->container->bound( $class )) {
            $this->container->instance( $class, $controller );
        }

        return $controller;
    }


    /**
     * Parse a requests accept header.
     *
     * @param Request|\Illuminate\Http\Request $request
     * @return array
     * @throws InvalidAcceptHeaderException
     */
    protected function parseAcceptHeader( Request $request )
    {
        if (preg_match( '#application/vnd\.' . app()->config[ 'dingo' ][ 'vendor' ] . '.(v[\d\.]+)\+(\w+)#',
            $request->header( 'accept' ), $matches )) {
            return array_slice( $matches, 1 );
        } elseif (app()->config[ 'dingo' ][ 'strict' ]) {
            throw new InvalidAcceptHeaderException( 'Unable to match the "Accept" header for the API request.' );
        }

        return [ app()->config[ 'dingo' ][ 'version' ], 'json' ];
    }


    /**
     * Handle a thrown routing exception.
     *
     * @param Request|\Illuminate\Http\Request $request
     * @param Exception|\Exception             $exception
     * @return \Illuminate\Http\Response
     * @throws Exception
     * @throws \Exception
     */
    protected function handleException( Request $request, Exception $exception )
    {
        if ($request instanceof InternalRequest) {
            throw $exception;
        } else {
            $response = $this->prepareResponse(
                $request,
                $this->events->until( 'router.exception', [ $exception ] )
            );

            // When an exception is thrown it halts execution of the dispatch. We'll
            // call the attached after filters for caught exceptions still.
            $this->callFilter( 'after', $request, $response );
        }

        return $response;
    }
}

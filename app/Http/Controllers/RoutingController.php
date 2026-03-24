<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoutingController extends Controller
{

    public function __construct()
    {
        // $this->
        // middleware('auth')->
        // except('index');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (Auth::user()) {
            return redirect('index');
        } else {
            return redirect('login');
        }
    }

    /**
     * Display a view based on first route param
     *
     * @return \Illuminate\Http\Response
     */
    public function root(Request $request, $first)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        if ($first == "assets")
            return redirect('home');

        // Don't treat static file requests as view names (e.g. icon-192.png, manifest.json)
        $staticExtensions = ['png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'webp', 'json', 'xml', 'txt', 'woff', 'woff2', 'ttf', 'css', 'js'];
        if (preg_match('/\.(' . implode('|', $staticExtensions) . ')$/i', $first)) {
            abort(404);
        }

        if (!view()->exists($first)) {
            abort(404);
        }

        return view($first, ['mode' => $mode, 'demo' => $demo]);
    }

    /**
     * second level route
     */
    public function secondLevel(Request $request, $first, $second)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        if ($first == "assets")
            return redirect('home');

        $viewName = $first . '.' . $second;

        if (!view()->exists($viewName)) {
            abort(404);
        }

        return view($viewName, ['mode' => $mode, 'demo' => $demo]);
    }

    /**
     * third level route
     */
    public function thirdLevelOld(Request $request, $first, $second, $third)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        if ($first == "assets")
            return redirect('home');

        $viewName = $first . '.' . $second . '.' . $third;

        if (!view()->exists($viewName)) {
            abort(404);
        }

        return view($viewName, ['mode' => $mode, 'demo' => $demo]);
    }

    public function thirdLevel(Request $request, $first, $second, $third)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Block access to sensitive or invalid paths
        if (
            Str::startsWith($first, '.') ||          // e.g., .well-known, .env
            in_array($first, ['storage', 'vendor', 'assets', 'admin']) ||
            Str::contains($first . $second . $third, ['..', '~', '\\'])
        ) {
            abort(404);
        }

        if ($first === 'assets') {
            return redirect('home');
        }

        // Construct view name
        $viewName = "$first.$second.$third";

        // Only render if the view exists
        if (!view()->exists($viewName)) {
            abort(404);
        }

        return view($viewName, compact('mode', 'demo'));
    }

}

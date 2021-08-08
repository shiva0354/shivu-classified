<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Models\Category;
use App\Models\Location;
use App\Models\Post;
use Illuminate\Http\Request;

class UserSearchController extends Controller
{

    /**
     * for universal search box
     * @param SearchRequest $request
     * @return Post $posts
     */
    public function search(SearchRequest $request)
    {
        $title = $request->query('query');
        $categoryId = $request->query('category');
        $locationId = $request->query('location');

        $category = Category::find($categoryId, ['id', 'name', 'slug']);
        $location = Location::find($locationId, ['id', 'name', 'slug']);

        $posts = Post::with(['firstImage', 'category' => function ($query) {
            $query->select('id', 'slug', 'name');
        }])->active()
            ->when($title, function ($query, $title) {
                return $query->where('title', 'LIKE', '%' . $title . '%');
            })
            ->when($categoryId, function ($query, $categoryId) {
                return $query->postCategory($categoryId);
            })
            ->when($locationId, function ($query, $locationId) {
                return $query->postCity($locationId);
            })
            ->paginate(9, ['id', 'category_id', 'title', 'price', 'created_at']);

        return view('user.products', compact('posts', 'category', 'location'));
    }

    /**
     * returing products when category is set
     * @param Category $category
     * @param int $category_id
     */
    public function categorySearch($category, $category_id)
    {
        $category = Category::findOrFail($category_id, ['id', 'slug', 'name']);
        $location = null;
        seo()->title(__("category-seo.seo-title:$category->name") . " in India");
        seo()->description(__("category-seo.seo-description:$category->name"));
        $posts = $category->posts()
            ->with(['firstImage', 'category' => function ($query) {
                $query->select('id', 'slug', 'name');
            }])
            ->active()
            ->paginate(9, ['id', 'category_id', 'title', 'price', 'created_at']);

        return view('user.products', compact('posts', 'category', 'location'));
    }

    /**
     * returning list of products when location is set
     * @param Location $location
     * @param int $locationId
     */
    public function LocationSearch($location, $locationId)
    {
        $category = null;
        $location = Location::findOrFail($locationId, ['id', 'slug', 'name']);
        $posts = $location->posts()
            ->with(['firstImage', 'category' => function ($query) {
                $query->select('id', 'slug', 'name');
            }])->active()
            ->paginate(9, ['id', 'category_id', 'title', 'price', 'created_at']);

        if ($posts->isEmpty()) {
            $posts = Post::with(['firstImage', 'category' => function ($query) {
                $query->select('id', 'slug', 'name');
            }])->active()
                ->postState($locationId)
                ->paginate(9, ['id', 'category_id', 'title', 'price', 'created_at']);
        }

        return view('user.products', compact('posts', 'category', 'location'));
    }

    /**
     * returning list of products when location and category both is set
     * @param Location $location
     * @param Category $category
     * @param int $locationId,$category_id
     */
    public function locationCategorySearch($location, $locationId, $category, $category_id)
    {
        $category = Category::findOrFail($category_id, ['id', 'slug', 'name']);
        $location = Location::findOrFail($locationId, ['id', 'slug', 'name']);

        $posts = $location->posts()
            ->with(['firstImage', 'category' => function ($query) {
                $query->select('id', 'slug', 'name');
            }])->active()
            ->postCategory($category_id)
            ->paginate(9, ['id', 'category_id', 'title', 'price', 'created_at']);

        if ($posts->isEmpty()) {
            $posts = Post::with(['firstImage', 'category' => function ($query) {
                $query->select('id', 'slug', 'name');
            }])->active()
                ->postState($locationId)
                ->postCategory($category_id)
                ->paginate(9, ['id', 'category_id', 'title', 'price', 'created_at']);
        }

        return view('user.products', compact('posts', 'category', 'location'));
    }

    /**
     * returning posts when location, locality and category are set
     * @param Location $location
     * @param string $locality
     * @param Category $category
     * @param int $locationId, $category_id
     */
    public function localityCategorySearch($location, $locationId, $locality, $category, $category_id)
    {
        $category = Category::findOrFail($category_id, ['id', 'slug', 'name']);
        $location = Location::findOrFail($locationId, ['id', 'slug', 'name']);
        $locality = str_replace('_', ' ', $locality);

        // $posts = Post::active()->where('category_id', $category_id)->whereLocality($locality)->paginate(9);
        $posts = $location->posts()
            ->with(['firstImage', 'category' => function ($query) {
                $query->select('id', 'slug', 'name');
            }])->active()
            ->postCategory($category_id)
            ->whereLocality($locality)
            ->paginate(9, ['id', 'category_id', 'title', 'price', 'created_at']);

        return view('user.products', compact('posts', 'category', 'location'));
    }

    /**
     * returning when location and locality are set
     * @param Location $location
     * @param string $locality
     * @param int $locationId
     */
    public function localitySearch($location, $locationId, $locality)
    {
        $category = null;
        $location = Location::findOrFail($locationId, ['id', 'slug', 'name']);
        $locality = str_replace('_', ' ', $locality);

        $posts = $location->posts()
            ->with(['firstImage', 'category' => function ($query) {
                $query->select('id', 'slug', 'name');
            }])->active()
            ->whereLocality($locality)
            ->paginate(9, ['id', 'category_id', 'title', 'price', 'created_at']);

        return view('user.products', compact('posts', 'category', 'location'));
    }

    /**
     * returning categories
     */
    public function ajaxCategory(Request $request)
    {
        $search = $request->input('search');
        $categories = Category::selectRaw('`id`, `name` as `text`')->where('name', 'like', '%' . $search . '%')->get();
        return response()->json($categories);
    }

    /**
     * returning locations
     */
    public function ajaxcities(Request $request)
    {
        $search = $request->input('search');

        $cities = Location::with('state')->whereNotNull('parent_id')->where('name', 'like', '%' . $search . '%')->get(['id', 'name', 'parent_id']);

        $response = array();
        foreach ($cities as $city) {
            $response[] = array(
                "id" => $city->id,
                "text" => $city->name . "," . $city->state->name
            );
        }

        return response()->json($response);
    }


    /**
     * returning titles
     */
    public function ajaxPostsTitle(Request $request)
    {
        $search = $request->input('search');
        $titles = Post::selectRaw('distinct `title` as text')->where('title', 'like', '%' . $search . '%')->take(1000)->get();
        return response()->json($titles);
    }
}

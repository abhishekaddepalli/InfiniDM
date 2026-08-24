<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Marketing blog posts CRUD. */
class BlogController extends Controller
{
    public function index()
    {
        $posts = BlogPost::latest()->paginate(20);
        $stats = [
            'published' => BlogPost::where('status', 'published')->count(),
            'draft'     => BlogPost::where('status', 'draft')->count(),
            'total'     => BlogPost::count(),
            'views'     => (int) BlogPost::sum('views'),
        ];
        return view('admin.blog.index', compact('posts', 'stats'));
    }

    public function create()
    {
        return view('admin.blog.form', ['post' => new BlogPost(['status' => 'draft'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $post = new BlogPost();
        $this->fill($post, $data);
        $post->save();
        return redirect()->route('admin.blog.index')->with('success', __('Post saved.'));
    }

    public function edit(BlogPost $post)
    {
        return view('admin.blog.form', compact('post'));
    }

    public function update(Request $request, BlogPost $post)
    {
        $data = $this->validated($request, $post->id);
        $this->fill($post, $data);
        $post->save();
        return redirect()->route('admin.blog.index')->with('success', __('Post updated.'));
    }

    public function destroy(BlogPost $post)
    {
        $post->delete();
        return back()->with('success', __('Post deleted.'));
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'title'   => ['required', 'string', 'max:180'],
            'slug'    => ['nullable', 'string', 'max:200', 'unique:blog_posts,slug' . ($ignoreId ? ',' . $ignoreId : '')],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body'    => ['nullable', 'string'],
            'author'  => ['nullable', 'string', 'max:120'],
            'status'  => ['required', 'in:draft,published'],
        ]);
    }

    private function fill(BlogPost $post, array $data): void
    {
        $post->fill([
            'title'   => $data['title'],
            'slug'    => filled($data['slug'] ?? null) ? $data['slug'] : Str::slug($data['title']) . '-' . Str::random(4),
            'excerpt' => $data['excerpt'] ?? null,
            'body'    => $data['body'] ?? null,
            'author'  => $data['author'] ?? null,
            'status'  => $data['status'],
        ]);

        if ($post->status === 'published' && ! $post->published_at) {
            $post->published_at = now();
        }
    }
}

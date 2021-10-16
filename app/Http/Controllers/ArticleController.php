<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Attachment;
use App\Http\Requests\ArticleRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Exception;

class ArticleController extends Controller
{
    public function __construct()
    {
        // アクションに合わせたpolicyのメソッドで認可されていないユーザーはエラーを投げる
        $this->authorizeResource(Article::class, 'article');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $articles = Article::all();
        return view('articles.index', compact('articles'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('articles.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(ArticleRequest $request)
    {
        // Articleのデータを用意
        $article = new Article();
        $article->fill($request->all());

        // ユーザーIDを追加
        $article->user_id = $request->user()->id;   

        // ファイルの用意
        $file = $request->file('file');

        // $name = $file->getClientOriginalName();
        DB::beginTransaction();

        try {
            // Article保存
            $article->save();

            // 画像ファイル保存
            if (!$path = Storage::putFile('articles', $file)) {
                throw new Exception('ファイルの保存に失敗しました');
            }

            // Attachmentモデルの情報を用意
            $attachment = new Attachment([
                'article_id' => $article->id,
                'org_name' => $file->getClientOriginalName(),
                'name' => basename($path)
            ]);

            // Attachment保存
            $attachment->save();
            DB::commit();
        } catch (\Exception $e) {

            if (!empty($path)) {
                Storage::delete($path);
            }
            DB::rollback();
            return back()
                ->withErrors($e->getMessage());
        }

        return redirect(route('articles.index'))
            ->with(['flash_message' => '登録が完了しました']);
    }


    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Article  $article
     * @return \Illuminate\Http\Response
     */
    public function show(Article $article)
    {
        return view('articles.show', compact('article'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Article  $article
     * @return \Illuminate\Http\Response
     */
    public function edit(Article $article)
    {

        return view('articles.edit', compact('article'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Article  $article
     * @return \Illuminate\Http\Response
     */
    public function update(ArticleRequest $request, Article $article)
    {


        // バリデーション
        $request->validate([
            'caption' => 'required|max:255',
            'info' => 'max:255'
        ]);

        // Articleのデータを更新
        //fill == fillable;
        $article->fill($request->all());

        // トランザクション開始
        // DB::beginTransaction();
        try {
            // Article保存 ここで問題がなければ return redirect まで飛ぶ。駄目だったらcatch
            $article->save();

            // トランザクション終了(成功)
            // DB::commit();
        } catch (\Exception $e) {
            // トランザクション終了(失敗) catchはエラーの内容によって何パターンも作れる。エラーの内容がException = エラーの中玉。 其の上はthrow(親玉 に含まれていれば返す。
            //どのエラーを拾いたいか？ガズルだけとか。色々決めれる。
            // DB::rollback();
            return back()->withErrors($e->getMessage());
        }

        return redirect()
            ->route('articles.index')
            ->with(['flash_message' => '更新が完了しました']);
    }


    //tryとtransactionの違いは？？ 管理しているものが違う tryはPHPのプログラム上のエラーを検知。。 Transactionはエラーを検知するものではなく。エラーがあれば戻す、もの。セーブポイントのようなもの。begin〜commitの間の処理を仮置DBの Aさんの口座→(送金)Bさんの口座 
    //どこかに送金失敗したら大変だから整合性DBのの中で取れたら完了。
    //DBの処理だけ

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Article  $article
     * @return \Illuminate\Http\Response
     */
    public function destroy(Article $article)
    {
        // DB::beginTransaction();


        //Article,Attach,FILE削除
        $path = $article->image_path;
        DB::beginTransaction();
        try {
            $article->delete(); //Article delete
            $article->attachment->delete(); //Attachment delete
            if (!Storage::delete($path)) {
                throw new Exception('ファイルの削除に失敗しました。');
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withErrors($e->getMessage());
        }
        return redirect()
            ->route('articles.index')
            ->with(['flash_message' => '削除が完了しました']);
    }
}

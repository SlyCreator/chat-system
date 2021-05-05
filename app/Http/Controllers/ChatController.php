<?php

namespace App\Http\Controllers\Chat;


use App\Events\FetchUserChats;
use App\Events\FetchUserMessage;
use App\Events\FetchUserMessageCount;
use App\Events\UnreadMessageCount;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Traits\Service;
use App\Models\User;
use App\Traits\UploadTrait;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChatResource;
use App\Http\Resources\MessageResource;
use Response;


class ChatController extends Controller
{
    use UploadTrait;

    // Send message to user
    public function sendMessage(Request $request)
    {
        // validate the request
        $this->validate($request, [
            'recipient' => ['required'],
           // 'body' => ['required']
        ]);

        $recipient = $request->recipient;
        $user = auth()->user();
        $body = $request->body;

        // check if there is an existing chat
        // between the auth user and the recipient
        $chat = $user->getChatWithUser($recipient);

        if(! $chat){
            $chat = Chat::create([]);
            $this->createParticipants($chat->id, [$user->id, $recipient]);
        }

        // add the message to the chat
        $message =new Message;
        $message->user_id = $user->id;
        $message->chat_id = $chat->id;
        $message->body = $body;
        $message->last_read = null;
        $message->msg_type = 1;

        if ($request->has('msg_file')) {
            $file = $request->file('msg_file');
            $name = time()."_". preg_replace('/\s+/', '_', strtolower($file->getClientOriginalName()));
            $folder = '/uploads/docs/';
            $filePath = $folder . $name;
            $message->body = $filePath;

            $allowedFileExtension=['jpeg','png','jpg','gif','svg'];
            $extension = $file->getClientOriginalExtension();
            $check=in_array($extension,$allowedFileExtension);
            if($check){
                $message->msg_type = 3;
            }else{
                $message->msg_type = 4;
            }

            // Upload image using queue to save time
            $this->uploadOne($file, $folder, 'public', $name);
        }
        $message->save();
        $this->markAsRead($chat->id);
        event(new FetchUserMessage($message));
        event(new FetchUserChats($chat));
        pushNotification($recipient,"Chat from ".$user->full_name,$message->body,"Chat",$message->user_id,
            $user->full_name,$user->profile_image,$chat->id);
        return new MessageResource($message);

    }

    // Get chats for user
    public function getUserChats()
    {
        $chats = $this->fetchUserChats();
        return ChatResource::collection($chats);
    }

    // get messages for chat
    public function getChatMessages($id)
    {
        $messages = Message::Where('chat_id', $id)->get();
        $this->markAsRead($id);
        return MessageResource::collection($messages);
    }

    // mark chat as read
    public function markAsRead($id)
    {
        $chat = Chat::find($id);
        $chat->markAsReadForUser(auth()->id());
        event(new FetchUserMessageCount());
        return response()->json(['message' => 'successful'], 200);
    }

    public function getChatID($id)
    {
        $user = auth()->user();
        $chat = $user->getChatWithUser($id);
        if(! $chat){
            return $this->errorResponse("No chat_id; this is your first chat",404);
        }
        $chat_id = $chat->pivot->chat_id;
        return $this->successResponse(['chat_id'=>$chat_id],"chat id retrieved successfully");
    }

    public function deleteAMessage($id)
    {
        $message = Message::findOrFail($id);
        $this->authorize('delete',$message);
        $message->delete();
        return $this->successResponse([],"Deleted Successfully");
    }

    public function downloadFile()
    {
       // $file_path = storage_path('app/public/uploads/docs') . "/" . $filename;
       // return Response::download($file_path);
    }
    private function createParticipants($chatId, array $data)
    {
        $chat = Chat::findOrFail($chatId);
        $chat->participants()->sync($data);
    }
    private function fetchUserChats()
    {
        return auth()->user()->chats()
            ->with(['messages', 'members'])
            ->orderBy('updated_at','DESC')
            ->get();

    }
}


////How do I fetch chat other by the most recent updated

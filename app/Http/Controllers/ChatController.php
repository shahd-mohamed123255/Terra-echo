<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Services\AgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(protected AgentService $agent) {}

    public function start(Request $request): JsonResponse
    {
        $user = $request->user();
        $farmerId = 'user_' . $user->id;

        $conversation = ChatConversation::where('user_id', $user->id)->latest('id')->first();

        // محادثة قديمة: رجّع الـ history
        if ($conversation) {
            $history = $this->agent->history($conversation->agent_conversation_id);

            if ($history['ok']) {
                return response()->json([
                    'success'  => true,
                    'existing' => true,
                    'messages' => $history['data']['messages'] ?? [],
                ]);
            }

            if (($history['code'] ?? '') !== 'conversation_not_found') {
                return $this->fail($history);
            }

            // الـ Agent مش فاكرها: امسحها وابدأ جديدة
            $conversation->delete();
        }

        $result = $this->agent->start($farmerId, $user->name, 'ar');

        if (! $result['ok']) {
            return $this->fail($result);
        }

        $data = $result['data'];

        ChatConversation::create([
            'user_id'               => $user->id,
            'agent_conversation_id' => $data['conversation_id'],
        ]);

        return response()->json([
            'success'  => true,
            'existing' => false,
            'messages' => [['role' => 'assistant', 'content' => $data['reply']]],
            'data'     => $data,
        ]);
    }

    public function message(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        // conversation_id بنقراه من الـ DB بس، مش من الـ browser
        $conversation = ChatConversation::where('user_id', $user->id)->latest('id')->first();

        if (! $conversation) {
            return response()->json([
                'success' => false,
                'code'    => 'no_conversation',
                'message' => 'ابدأ المحادثة الأول.',
            ], 409);
        }

        $result = $this->agent->chat(
            'user_' . $user->id,
            $conversation->agent_conversation_id,
            trim($validated['message'])
        );

        if (! $result['ok']) {
            return $this->fail($result);
        }

        return response()->json(['success' => true, 'data' => $result['data']]);
    }

    protected function fail(array $result): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code'    => $result['code'] ?? 'error',
            'message' => $result['message'] ?? 'حصلت مشكلة.',
        ], $result['status'] ?? 500);
    }
}
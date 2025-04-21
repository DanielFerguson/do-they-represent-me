import type { VercelRequest, VercelResponse } from '@vercel/node';

export default function handler(
    request: VercelRequest,
    response: VercelResponse,
) {
    const THEYVOTEFORYOU_API_KEY = process.env.THEYVOTEFORYOU_API_KEY;

    fetch(`https://theyvoteforyou.org.au/api/v1/people/${request.query.id}.json?key=${THEYVOTEFORYOU_API_KEY}`)
        .then((response) => response.json())
        .then((data) => {
            response.status(200).json(data);
        });
}
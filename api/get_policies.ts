export function GET(request: Request) {
    const THEYVOTEFORYOU_API_KEY = process.env.THEYVOTEFORYOU_API_KEY;

    fetch(`https://theyvoteforyou.org.au/api/v1/people/${request.query.id}.json?key=${THEYVOTEFORYOU_API_KEY}`)
        .then((response) => response.json())
        .then((data) => {
            return new Response(JSON.stringify(data));
        });
}
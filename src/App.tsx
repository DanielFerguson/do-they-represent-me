import './App.css'
import { Fragment, useEffect, useState } from 'react'
import { people } from './lib/people'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Combobox } from '@/components/ui/combobox'
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion"

type Person = {
  id: number,
  latest_member: {
    id: number,
    name: {
      first: string,
      last: string
    }
    electorate: string,
    house: string,
    party: string
  },
}

type Policy = {
  id: number,
  name: string,
  description: string,
  provisional: boolean,
  last_edited_at: string
}

type PolicyComparison = {
  policy: Policy,
  agreement: string,
  voted: boolean
}

type PersonDetails = {
  policy_comparisons: PolicyComparison[]
}

type UserVote = 'approve' | 'reject' | 'unsure' | null; // Type for user votes

const THEYVOTEFORYOU_API_KEY = 'V8Dwp5hntNFnts9kncuE'

function App() {
  const [selectedPerson, setSelectedPerson] = useState<Person | null>(null);
  const [personDetails, setPersonDetails] = useState<PersonDetails | null>(null);
  const [currentPolicyIndex, setCurrentPolicyIndex] = useState(0);
  const [userVotes, setUserVotes] = useState<UserVote[]>([]);
  const [showResults, setShowResults] = useState(false);
  const [alignmentResult, setAlignmentResult] = useState<AlignmentResult | null>(null);

  const formattedPeople = people.map((person) => ({
    value: `${person.latest_member.name.first} ${person.latest_member.name.last}`,
    label: `${person.latest_member.name.first} ${person.latest_member.name.last}`
  }));

  // Fetch person details effect
  useEffect(() => {
    if (!selectedPerson) {
      setPersonDetails(null); // Clear details if no person selected
      setAlignmentResult(null); // Clear results
      return;
    }

    // Reset voting state before fetching new details
    setCurrentPolicyIndex(0);
    setUserVotes([]);
    setShowResults(false);
    setPersonDetails(null); // Indicate loading
    setAlignmentResult(null); // Clear previous results

    // Fetch the person's details
    fetch(`https://theyvoteforyou.org.au/api/v1/people/${selectedPerson.id}.json?key=${THEYVOTEFORYOU_API_KEY}`)
      .then((response) => response.json())
      .then((data: PersonDetails) => {
        // --- Sort policies by last_edited_at descending ---
        const sortedComparisons = [...data.policy_comparisons].sort((a, b) => {
          // ISO 8601 strings can be compared directly for descending order (newer first)
          return b.policy.last_edited_at.localeCompare(a.policy.last_edited_at);
        });

        const sortedData = { ...data, policy_comparisons: sortedComparisons };
        setPersonDetails(sortedData);
        // Initialize votes array based on the *sorted* data length
        setUserVotes(new Array(sortedData.policy_comparisons.length).fill(null));
      })
      .catch(error => {
        console.error("Failed to fetch person details:", error);
        // Handle error state if needed
        setPersonDetails(null);
        setAlignmentResult(null);
      });
  }, [selectedPerson]);

  // Use the sorted policy comparisons from state
  const sortedPolicyComparisons = personDetails?.policy_comparisons ?? [];
  const totalPolicies = sortedPolicyComparisons.length;

  const handleVote = (vote: UserVote) => {
    if (!personDetails || vote === null) return;

    const nextVotes = [...userVotes];
    nextVotes[currentPolicyIndex] = vote;
    setUserVotes(nextVotes);

    if (currentPolicyIndex < totalPolicies - 1) {
      setCurrentPolicyIndex(currentPolicyIndex + 1);
    } else {
      // Calculate results immediately when the last vote is cast
      setAlignmentResult(calculateAlignment());
      setShowResults(true); // All policies voted on
    }
  };

  // --- Handler to show results early ---
  const handleShowResults = () => {
    if (totalPolicies > 0) { // Only show results if there are policies
      // Calculate results when 'Done' is clicked
      setAlignmentResult(calculateAlignment());
      setShowResults(true);
    }
  }

  const handleUnvote = () => { // Renamed to handleBack for clarity
    if (!personDetails) return;

    if (showResults) {
      // --- Back from Results Screen ---
      // Find the index of the last policy the user actually voted on (not null).
      // Replace findLastIndex with a loop for broader compatibility
      let lastVotedIndex = -1;
      for (let i = userVotes.length - 1; i >= 0; i--) {
        if (userVotes[i] !== null) {
          lastVotedIndex = i;
          break;
        }
      }

      if (lastVotedIndex !== -1) {
        // If votes exist, go back to the index of the last vote cast, allowing change.
        setCurrentPolicyIndex(lastVotedIndex);
      } else {
        // If no votes were cast (e.g., clicked 'Done' immediately), go back to the first policy.
        setCurrentPolicyIndex(0);
      }
      setShowResults(false); // Go back to voting view
      setAlignmentResult(null); // Clear results when going back to vote

    } else if (currentPolicyIndex > 0) {
      // --- Back during Voting ---
      // Go back one step (to the previous index) without clearing the vote.
      const prevIndex = currentPolicyIndex - 1;
      setCurrentPolicyIndex(prevIndex);
    }
    // If currentPolicyIndex is 0 during voting, 'Back' does nothing.
  };


  // --- Calculate detailed alignment results ---
  // Define a type for the detailed alignment results (Updated)
  type AlignmentResult = {
    score: number;
    comparableVotes: number;
    totalAnswered: number;
    // Store more details for breakdown
    agreedPolicies: { policy: Policy; userVote: 'approve' | 'reject'; repAgrees: boolean }[];
    disagreedPolicies: { policy: Policy; userVote: 'approve' | 'reject'; repAgrees: boolean }[];
    userUnsurePolicies: { policy: Policy; repAgrees: boolean }[];
  };

  const calculateAlignment = (): AlignmentResult => {
    const defaultResult: AlignmentResult = {
      score: 0,
      comparableVotes: 0,
      totalAnswered: 0,
      agreedPolicies: [],
      disagreedPolicies: [],
      userUnsurePolicies: [],
    };

    if (!personDetails || userVotes.length !== totalPolicies) {
      return defaultResult;
    }

    let agreements = 0;
    let comparableVotes = 0;
    let totalAnswered = 0;
    // Initialize with the updated type
    const agreedPolicies: { policy: Policy; userVote: 'approve' | 'reject'; repAgrees: boolean }[] = [];
    const disagreedPolicies: { policy: Policy; userVote: 'approve' | 'reject'; repAgrees: boolean }[] = [];
    const userUnsurePolicies: { policy: Policy; repAgrees: boolean }[] = [];

    sortedPolicyComparisons.forEach((comparison, index) => {
      const userVote = userVotes[index];
      const policy = comparison.policy;

      if (userVote !== null) {
        totalAnswered++;
      }

      // API uses "100" for agreement, "0.0" for disagreement on the policy statement
      const repAgreesWithPolicy = parseFloat(comparison.agreement) === 100;

      if (userVote === 'approve' || userVote === 'reject') {
        comparableVotes++;
        // Agreement: User approves AND rep agrees, OR User rejects AND rep disagrees
        if ((userVote === 'approve' && repAgreesWithPolicy) || (userVote === 'reject' && !repAgreesWithPolicy)) {
          agreements++;
          // Store details for breakdown
          agreedPolicies.push({ policy, userVote, repAgrees: repAgreesWithPolicy });
        } else {
          // Disagreement: User approves AND rep disagrees, OR User rejects AND rep agrees
          // Store details for breakdown
          disagreedPolicies.push({ policy, userVote, repAgrees: repAgreesWithPolicy });
        }
      } else if (userVote === 'unsure') {
        // Track policies where the user was unsure and the representative's stance
        userUnsurePolicies.push({ policy, repAgrees: repAgreesWithPolicy });
      }
      // Cases where userVote is null are ignored for scoring and breakdown lists
    });

    const score = comparableVotes > 0 ? Math.round((agreements / comparableVotes) * 100) : 0;

    return {
      score,
      comparableVotes,
      totalAnswered,
      agreedPolicies,
      disagreedPolicies,
      userUnsurePolicies,
    };
  };

  // Get data for the current policy based on the index and sorted list
  const currentPolicyData = sortedPolicyComparisons[currentPolicyIndex];
  const currentPolicy = currentPolicyData?.policy;

  // Determine if the Back button should be disabled
  const isBackDisabled = totalPolicies === 0 ||
    (!showResults && currentPolicyIndex === 0) || // Can't go back from first question during voting
    (showResults && userVotes.every(vote => vote === null)); // Can't go back from results if nothing was voted on

  // Determine if the Done button should be disabled (only shown during voting)
  const isDoneDisabled = showResults || totalPolicies === 0; // Disable if already showing results or no policies

  const repLastName = selectedPerson?.latest_member.name.last ?? 'Rep'; // Fallback name

  return (
    <Fragment>
      {/* Search for a representative */}
      <Card className='w-[500px] mx-auto text-left mb-4'>
        <CardHeader>
          <CardTitle>Do They Represent Me?</CardTitle>
          <CardDescription>Find your representative by searching for their name.</CardDescription>
        </CardHeader>
        <CardContent>
          <Combobox
            options={formattedPeople}
            value={selectedPerson ? `${selectedPerson.latest_member.name.first} ${selectedPerson.latest_member.name.last}` : ''}
            setValue={(value) => {
              const person = people.find((p) => `${p.latest_member.name.first} ${p.latest_member.name.last}` === value);
              setSelectedPerson(person || null); // Set to null if not found
            }}
            searchPlaceholder='Search for a representative'
            noResultsMessage='No representatives found'
          />
        </CardContent>
      </Card>

      {/* Show loading state */}
      {selectedPerson && !personDetails && (
        <Card className='w-[500px] mx-auto text-left'>
          <CardContent className="pt-6">Loading policy data...</CardContent>
        </Card>
      )}

      {/* Show policy voting or results */}
      {personDetails && totalPolicies > 0 && (
        <Card className='w-[500px] mx-auto text-left'>
          <CardHeader>
            <CardTitle>Policy Alignment</CardTitle>
            <CardDescription>
              {showResults
                ? `Here's how your votes compare to ${selectedPerson?.latest_member.name.first} ${selectedPerson?.latest_member.name.last}.`
                // Show progress based on current index during voting
                : `Vote on the following policies to see how you align. Policy ${currentPolicyIndex + 1} of ${totalPolicies}.`}
            </CardDescription>
          </CardHeader>
          <CardContent>
            {/* Voting View */}
            {!showResults && currentPolicy && (
              <div className="space-y-4">
                {/* Show progress based on current index */}
                <Progress value={((currentPolicyIndex + 1) / totalPolicies) * 100} className="w-full mb-4" />
                <h3 className="font-semibold text-lg">{currentPolicy.name}</h3>
                <p className="text-sm text-muted-foreground">{currentPolicy.description}</p>
                <div className="flex justify-between items-center pt-4 border-t mt-4">
                  {/* Back Button */}
                  <div className="flex items-center gap-2">
                    <Button variant="outline" onClick={handleUnvote} disabled={isBackDisabled}>
                      Back
                    </Button>
                    <Button variant="secondary" onClick={handleShowResults} disabled={isDoneDisabled}>
                      Done
                    </Button>
                  </div>

                  {/* Voting Actions Group */}
                  <div className="flex items-center gap-2">
                    <div
                      className="flex gap-2"
                      onKeyDown={(event) => {
                        if (event.key === 'ArrowLeft') {
                          event.preventDefault();
                          handleVote('reject');
                        } else if (event.key === 'ArrowUp') {
                          event.preventDefault();
                          handleVote('unsure');
                        } else if (event.key === 'ArrowRight') {
                          event.preventDefault();
                          handleVote('approve');
                        }
                      }}
                      tabIndex={0} // Make the div focusable to receive key events
                    >
                      <Button variant="destructive" onClick={() => handleVote('reject')}>
                        Reject
                      </Button>
                      <Button variant="secondary" onClick={() => handleVote('unsure')}>
                        Unsure
                      </Button>
                      <Button variant="default" onClick={() => handleVote('approve')}>
                        Approve
                      </Button>
                    </div>
                  </div>
                </div>
              </div>
            )}
            {/* Results View */}
            {showResults && alignmentResult && (
              <div className="space-y-4">
                <h3 className="font-semibold text-lg text-center">
                  {/* Adjust title based on whether all policies were answered */}
                  {alignmentResult.totalAnswered === totalPolicies ? "Voting Complete!" : `Results based on ${alignmentResult.totalAnswered} answered policies`}
                </h3>
                {/* Show progress based on answered questions relative to total */}
                <Progress value={(alignmentResult.totalAnswered / totalPolicies) * 100} className="w-full mb-4" />
                <p className="text-center">
                  Based on the {alignmentResult.totalAnswered} policies you voted on,
                  you agreed with {selectedPerson?.latest_member.name.first} {selectedPerson?.latest_member.name.last} on{' '}
                  <span className="font-bold text-xl">{alignmentResult.score}%</span> of the {alignmentResult.comparableVotes} policies where you both expressed a clear stance (Approve/Reject).
                </p>

                {/* --- Breakdown Section --- */}
                <div className="pt-4 text-left">
                  <h4 className="font-semibold mb-2 text-center">Breakdown:</h4>
                  <Accordion type="single" collapsible className="w-full">
                    {/* Agreements */}
                    {alignmentResult.agreedPolicies.length > 0 && (
                      <AccordionItem value="agreements">
                        <AccordionTrigger>
                          Agreements ({alignmentResult.agreedPolicies.length})
                        </AccordionTrigger>
                        <AccordionContent>
                          <ul className="list-disc pl-5 space-y-1 text-sm">
                            {alignmentResult.agreedPolicies.map(({ policy, userVote }) => (
                              <li key={policy.id}>
                                {policy.name}
                                <span className="text-xs text-muted-foreground ml-2">
                                  {userVote === 'approve' ? '(You both Approved)' : '(You both Rejected)'}
                                </span>
                              </li>
                            ))}
                          </ul>
                        </AccordionContent>
                      </AccordionItem>
                    )}

                    {/* Disagreements */}
                    {alignmentResult.disagreedPolicies.length > 0 && (
                      <AccordionItem value="disagreements">
                        <AccordionTrigger>
                          Disagreements ({alignmentResult.disagreedPolicies.length})
                        </AccordionTrigger>
                        <AccordionContent>
                          <ul className="list-disc pl-5 space-y-1 text-sm">
                            {alignmentResult.disagreedPolicies.map(({ policy, userVote }) => (
                              <li key={policy.id}>
                                {policy.name}
                                <span className="text-xs text-muted-foreground ml-2">
                                  {userVote === 'approve' // Implies repAgrees is false here
                                    ? `(You Approved, ${repLastName} Rejected)`
                                    : `(You Rejected, ${repLastName} Approved)` // Implies repAgrees is true here
                                  }
                                </span>
                              </li>
                            ))}
                          </ul>
                        </AccordionContent>
                      </AccordionItem>
                    )}

                    {/* User was Unsure */}
                    {alignmentResult.userUnsurePolicies.length > 0 && (
                      <AccordionItem value="unsure">
                        <AccordionTrigger>
                          You Were Unsure ({alignmentResult.userUnsurePolicies.length})
                        </AccordionTrigger>
                        <AccordionContent>
                          <ul className="list-disc pl-5 space-y-1 text-sm">
                            {alignmentResult.userUnsurePolicies.map(({ policy, repAgrees }) => (
                              <li key={policy.id}>
                                {policy.name}
                                <span className="text-xs text-muted-foreground ml-2">
                                  ({repLastName} {repAgrees ? 'Approved' : 'Rejected'})
                                </span>
                              </li>
                            ))}
                          </ul>
                        </AccordionContent>
                      </AccordionItem>
                    )}
                  </Accordion>
                  {/* Message if no comparable votes */}
                  {alignmentResult.comparableVotes === 0 && alignmentResult.totalAnswered > 0 && (
                    <p className="text-sm text-muted-foreground text-center mt-4">No policies with clear Approve/Reject votes from you to compare.</p>
                  )}
                  {/* Message if no votes at all */}
                  {alignmentResult.totalAnswered === 0 && (
                    <p className="text-sm text-muted-foreground text-center mt-4">You didn't vote on any policies.</p>
                  )}
                </div>
                {/* --- End Breakdown Section --- */}

                <div className="flex justify-center gap-4 pt-4 border-t mt-4">
                  {/* Back Button (now labeled differently) */}
                  <Button variant="outline" onClick={handleUnvote} disabled={isBackDisabled}>
                    Go Back
                  </Button>
                  <Button onClick={() => {
                    // Reset voting for the same person
                    setCurrentPolicyIndex(0);
                    // Ensure userVotes is reset based on the *sorted* length
                    setUserVotes(new Array(totalPolicies).fill(null));
                    setShowResults(false);
                    setAlignmentResult(null); // Clear results for vote again
                  }}>Vote Again</Button>
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      )}
      {/* No policies available message */}
      {personDetails && totalPolicies === 0 && (
        <Card className='w-[500px] mx-auto text-left'>
          <CardContent className="pt-6">No policy voting data available for this representative.</CardContent>
        </Card>
      )}
    </Fragment>
  )
}

export default App

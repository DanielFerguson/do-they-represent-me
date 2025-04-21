import { useEffect, useState } from 'react'
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
import { RotateCw, ThumbsDownIcon, ThumbsUpIcon } from 'lucide-react';

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

  // Effect to fetch and process representative details when selection changes.
  useEffect(() => {
    if (!selectedPerson) {
      setPersonDetails(null);
      setAlignmentResult(null);
      return;
    }

    // Reset state for the new representative selection.
    setCurrentPolicyIndex(0);
    setUserVotes([]);
    setShowResults(false);
    setPersonDetails(null); // Use null to indicate loading state.
    setAlignmentResult(null);

    fetch(`https://theyvoteforyou.org.au/api/v1/people/${selectedPerson.id}.json?key=${THEYVOTEFORYOU_API_KEY}`)
      .then((response) => response.json())
      .then((data: PersonDetails) => {
        // Process policies: Sort by date and limit to the latest 50.
        const sortedComparisons = [...data.policy_comparisons]
          .sort((a, b) => {
            // ISO 8601 date strings can be compared lexicographically for recency.
            return b.policy.last_edited_at.localeCompare(a.policy.last_edited_at);
          })
          .slice(0, 50); // Limit the number of policies presented to the user.

        const sortedData = { ...data, policy_comparisons: sortedComparisons };
        setPersonDetails(sortedData);
        // Initialize user votes array to match the number of fetched policies.
        setUserVotes(new Array(sortedData.policy_comparisons.length).fill(null));
      })
      .catch(error => {
        console.error("Failed to fetch person details:", error);
        // TODO: Implement more robust error handling UI.
        setPersonDetails(null);
        setAlignmentResult(null);
      });
  }, [selectedPerson]);

  // Memoize or derive directly? Current approach uses state derived value.
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
      // Auto-calculate and show results when the last policy is voted on.
      setAlignmentResult(calculateAlignment());
      setShowResults(true);
    }
  };

  // Allows the user to view results before voting on all policies.
  // const handleShowResults = () => {
  //   if (totalPolicies > 0) {
  //     setAlignmentResult(calculateAlignment());
  //     setShowResults(true);
  //   }
  // }

  // Handles navigation back during voting or from the results screen.
  const handleUnvote = () => {
    if (!personDetails) return;

    if (showResults) {
      // Navigate back from results: Go to the last voted policy or the first if none were voted.
      // Manual implementation of findLastIndex for broader browser compatibility.
      let lastVotedIndex = -1;
      for (let i = userVotes.length - 1; i >= 0; i--) {
        if (userVotes[i] !== null) {
          lastVotedIndex = i;
          break;
        }
      }

      if (lastVotedIndex !== -1) {
        // Return user to the last policy they actively voted on.
        setCurrentPolicyIndex(lastVotedIndex);
      } else {
        // If user clicked 'Done' without voting, return to the first policy.
        setCurrentPolicyIndex(0);
      }
      setShowResults(false);
      setAlignmentResult(null); // Clear results when returning to voting.

    } else if (currentPolicyIndex > 0) {
      // Navigate back during voting: Go to the previous policy.
      const prevIndex = currentPolicyIndex - 1;
      setCurrentPolicyIndex(prevIndex);
    }
    // Intentionally do nothing if on the first policy during voting.
  };


  // Defines the structure for storing detailed alignment calculation results.
  type AlignmentResult = {
    score: number; // Percentage agreement on comparable votes.
    comparableVotes: number; // Count of policies where both user and rep had clear stance (Approve/Reject).
    totalAnswered: number; // Count of policies the user voted on (including 'Unsure').
    // Detailed lists for result breakdown:
    agreedPolicies: { policy: Policy; userVote: 'approve' | 'reject'; repAgrees: boolean }[];
    disagreedPolicies: { policy: Policy; userVote: 'approve' | 'reject'; repAgrees: boolean }[];
    userUnsurePolicies: { policy: Policy; repAgrees: boolean }[];
  };

  // Calculates the alignment score and detailed breakdown based on user votes and representative data.
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
      // Should not happen in normal flow, but guards against inconsistent state.
      console.warn("Attempted to calculate alignment with inconsistent data.");
      return defaultResult;
    }

    let agreements = 0;
    let comparableVotes = 0;
    let totalAnswered = 0;
    const agreedPolicies: AlignmentResult['agreedPolicies'] = [];
    const disagreedPolicies: AlignmentResult['disagreedPolicies'] = [];
    const userUnsurePolicies: AlignmentResult['userUnsurePolicies'] = [];

    sortedPolicyComparisons.forEach((comparison, index) => {
      const userVote = userVotes[index];
      const policy = comparison.policy;

      if (userVote !== null) {
        totalAnswered++;
      }

      // Note: The API represents agreement/disagreement with "100" or "0.0" strings.
      const repAgreesWithPolicy = parseFloat(comparison.agreement) === 100;

      if (userVote === 'approve' || userVote === 'reject') {
        // Only count towards score if both user and rep have a clear stance.
        comparableVotes++;
        const userAgreesWithRep = (userVote === 'approve' && repAgreesWithPolicy) || (userVote === 'reject' && !repAgreesWithPolicy);

        if (userAgreesWithRep) {
          agreements++;
          agreedPolicies.push({ policy, userVote, repAgrees: repAgreesWithPolicy });
        } else {
          disagreedPolicies.push({ policy, userVote, repAgrees: repAgreesWithPolicy });
        }
      } else if (userVote === 'unsure') {
        // Track policies where the user was unsure for the breakdown.
        userUnsurePolicies.push({ policy, repAgrees: repAgreesWithPolicy });
      }
      // Policies where userVote is null (skipped) are ignored.
    });

    // Avoid division by zero if no comparable votes exist.
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

  const currentPolicyData = sortedPolicyComparisons[currentPolicyIndex];
  const currentPolicy = currentPolicyData?.policy;

  // Logic to disable the 'Back' button appropriately.
  const isBackDisabled = totalPolicies === 0 ||
    (!showResults && currentPolicyIndex === 0) || // Cannot go back from the first policy during voting.
    (showResults && userVotes.every(vote => vote === null)); // Cannot go back from results if no votes were cast.

  // // Logic to disable the 'Done' button (only relevant during voting).
  // const isDoneDisabled = showResults || totalPolicies === 0; // Disable if results are shown or no policies exist.

  // Provide a fallback for the representative's last name in UI text.
  const repLastName = selectedPerson?.latest_member.name.last ?? 'Rep';

  return (
    <div className="min-h-screen bg-background text-foreground">
      <nav className="bg-primary text-primary-foreground p-4 shadow-md">
        <div className="container mx-auto flex justify-center sm:justify-start">
          <h1 className="text-2xl font-bold">Do They Represent Me?</h1>
        </div>
      </nav>

      <main className="p-4 grid grid-cols-1 gap-4">
        {/* Representative Selection Card */}
        <Card className='w-full max-w-lg mx-auto text-left'>
          <CardHeader>
            <CardTitle>Find your representative</CardTitle>
            <CardDescription>Find your representative by searching for their name.</CardDescription>
          </CardHeader>
          <CardContent>
            <Combobox
              options={formattedPeople}
              value={selectedPerson ? `${selectedPerson.latest_member.name.first} ${selectedPerson.latest_member.name.last}` : ''}
              setValue={(value) => {
                const person = people.find((p) => `${p.latest_member.name.first} ${p.latest_member.name.last}` === value);
                setSelectedPerson(person || null);
              }}
              searchPlaceholder='Search for a representative'
              noResultsMessage='No representatives found'
            />
          </CardContent>
        </Card>

        {/* Loading Indicator */}
        {selectedPerson && !personDetails && (
          <Card className='w-full max-w-lg mx-auto text-left'>
            <CardContent className='flex items-center gap-2'>
              <RotateCw className='animate-spin size-4' />
              <span>Loading policy data...</span>
            </CardContent>
          </Card>
        )}

        {/* Policy Voting / Results Card */}
        {personDetails && totalPolicies > 0 && (
          <Card className='w-full max-w-lg mx-auto text-left'>
            <CardHeader>
              <CardTitle>Policy alignment</CardTitle>
              <CardDescription>
                {showResults
                  ? `Here's how your votes compare to ${selectedPerson?.latest_member.name.first} ${selectedPerson?.latest_member.name.last}.`
                  : `Vote on the following policies to see how you align. Policy ${currentPolicyIndex + 1} of ${totalPolicies}.`}
              </CardDescription>
            </CardHeader>
            <CardContent>
              {/* Voting Interface */}
              {!showResults && currentPolicy && (
                <div className="space-y-4">
                  <Progress value={((currentPolicyIndex + 1) / totalPolicies) * 100} className="w-full mb-4" />
                  <h3 className="font-semibold text-lg">{currentPolicy.name}</h3>
                  <p className="text-sm text-muted-foreground">{currentPolicy.description}</p>
                  <div className="flex flex-col sm:flex-row sm:justify-between items-center gap-4 pt-4 border-t mt-4">
                    {/* Voting Actions (Reject/Unsure/Approve) */}
                    <div className="flex w-full sm:w-auto items-center justify-between md:justify-end gap-2">
                      <Button variant="outline" onClick={handleUnvote} disabled={isBackDisabled}>
                        Back
                      </Button>

                      <div className="grow"></div>

                      <Button variant="secondary" onClick={() => handleVote('unsure')}>
                        Unsure
                      </Button>
                      <Button variant="destructive" onClick={() => handleVote('reject')}>
                        <ThumbsDownIcon className='size-4' />
                      </Button>
                      <Button variant="secondary" onClick={() => handleVote('approve')}>
                        <ThumbsUpIcon className='size-4' />
                      </Button>
                    </div>
                    {/* Navigation Controls (Back/Done) */}
                    {/* <div className="flex w-full sm:w-auto items-center justify-between sm:justify-start gap-2">
                      <Button variant="outline" onClick={handleUnvote} disabled={isBackDisabled}>
                        Back
                      </Button>
                      <Button variant="secondary" onClick={handleShowResults} disabled={isDoneDisabled}>
                        Done
                      </Button>
                    </div> */}
                  </div>
                </div>
              )}
              {/* Results Display */}
              {showResults && alignmentResult && (
                <div className="space-y-4">
                  <h3 className="font-semibold text-lg text-center">
                    {alignmentResult.totalAnswered === totalPolicies ? "Voting Complete!" : `Results based on ${alignmentResult.totalAnswered} answered policies`}
                  </h3>
                  <Progress value={(alignmentResult.totalAnswered / totalPolicies) * 100} className="w-full mb-4" />
                  <p className="text-center">
                    Based on the {alignmentResult.totalAnswered} policies you voted on,
                    you agreed with {selectedPerson?.latest_member.name.first} {selectedPerson?.latest_member.name.last} on{' '}
                    <span className="font-bold text-xl">{alignmentResult.score}%</span> of the {alignmentResult.comparableVotes} policies where you both expressed a clear stance (Approve/Reject).
                  </p>

                  {/* Results Breakdown Section */}
                  <div className="pt-4 text-left">
                    <h4 className="font-semibold mb-2 text-center">Breakdown:</h4>
                    <Accordion type="single" collapsible className="w-full">
                      {/* Agreements Breakdown */}
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

                      {/* Disagreements Breakdown */}
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
                                    {userVote === 'approve' // Implies repAgrees is false in disagreement case
                                      ? `(You Approved, ${repLastName} Rejected)`
                                      : `(You Rejected, ${repLastName} Approved)` // Implies repAgrees is true in disagreement case
                                    }
                                  </span>
                                </li>
                              ))}
                            </ul>
                          </AccordionContent>
                        </AccordionItem>
                      )}

                      {/* User Unsure Breakdown */}
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
                    {/* Edge case messages for results */}
                    {alignmentResult.comparableVotes === 0 && alignmentResult.totalAnswered > 0 && (
                      <p className="text-sm text-muted-foreground text-center mt-4">No policies with clear Approve/Reject votes from you to compare.</p>
                    )}
                    {alignmentResult.totalAnswered === 0 && (
                      <p className="text-sm text-muted-foreground text-center mt-4">You didn't vote on any policies.</p>
                    )}
                  </div>

                  {/* Results Actions */}
                  <div className="flex flex-wrap justify-center gap-4 pt-4 border-t mt-4">
                    <Button variant="outline" onClick={handleUnvote} disabled={isBackDisabled}>
                      Go Back
                    </Button>
                    <Button onClick={() => {
                      // Reset state to allow voting again for the same representative.
                      setCurrentPolicyIndex(0);
                      // Ensure userVotes is reset based on the number of policies.
                      setUserVotes(new Array(totalPolicies).fill(null));
                      setShowResults(false);
                      setAlignmentResult(null);
                    }}>Vote Again</Button>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        )}

        {/* Message when no policy data is available for the selected representative */}
        {personDetails && totalPolicies === 0 && (
          <Card className='w-full max-w-lg mx-auto text-left'>
            <CardContent className="pt-6">No policy voting data available for this representative.</CardContent>
          </Card>
        )}
      </main>
    </div >
  )
}

export default App
